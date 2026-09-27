<?php

namespace App\Services;

use Exception;
use Mike42\Escpos\PrintConnectors\PrintConnector;

class WindowsSpoolPrintConnector implements PrintConnector
{
    private $buffer = [];
    private $printerName;

    public function __construct(string $printerName)
    {
        $this->printerName = $printerName;
    }

    public function __destruct()
    {
        if ($this->buffer !== null) {
            trigger_error("Print connector was not finalized. Did you forget to close the printer?", E_USER_NOTICE);
        }
    }

    public function finalize()
    {
        $data = implode('', $this->buffer);
        $this->buffer = null;

        $tempFile = tempnam(sys_get_temp_dir(), 'escpos');
        file_put_contents($tempFile, $data);

        // Usar la API de Spooler de Windows mediante PowerShell y C#
        $script = <<<POWERSHELL
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class PrintHelper {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public class DOCINFOA {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }
    [DllImport("winspool.Drv", EntryPoint="OpenPrinterA", SetLastError=true, CharSet=CharSet.Ansi, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool OpenPrinter([MarshalAs(UnmanagedType.LPStr)] string szPrinter, out IntPtr hPrinter, IntPtr pd);
    [DllImport("winspool.Drv", EntryPoint="ClosePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool ClosePrinter(IntPtr hPrinter);
    [DllImport("winspool.Drv", EntryPoint="StartDocPrinterA", SetLastError=true, CharSet=CharSet.Ansi, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, Int32 level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);
    [DllImport("winspool.Drv", EntryPoint="EndDocPrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);
    [DllImport("winspool.Drv", EntryPoint="StartPagePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);
    [DllImport("winspool.Drv", EntryPoint="EndPagePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);
    [DllImport("winspool.Drv", EntryPoint="WritePrinter", SetLastError=true, ExactSpelling=true, CallingConvention=CallingConvention.StdCall)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, Int32 dwCount, out Int32 dwWritten);

    public static bool PrintFile(string printerName, string fileName) {
        bool success = false;
        IntPtr hPrinter = IntPtr.Zero;
        DOCINFOA di = new DOCINFOA();
        di.pDocName = "ERP Ticket RAW";
        di.pDataType = "RAW";
        if (OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) {
            if (StartDocPrinter(hPrinter, 1, di)) {
                if (StartPagePrinter(hPrinter)) {
                    byte[] bytes = System.IO.File.ReadAllBytes(fileName);
                    IntPtr pBytes = Marshal.AllocCoTaskMem(bytes.Length);
                    Marshal.Copy(bytes, 0, pBytes, bytes.Length);
                    Int32 dwWritten = 0;
                    success = WritePrinter(hPrinter, pBytes, bytes.Length, out dwWritten);
                    Marshal.FreeCoTaskMem(pBytes);
                    EndPagePrinter(hPrinter);
                }
                EndDocPrinter(hPrinter);
            }
            ClosePrinter(hPrinter);
        }
        return success;
    }
}
"@
\$result = [PrintHelper]::PrintFile("{$this->printerName}", "{$tempFile}")
if (!\$result) {
    Write-Output "Fallo al enviar a la cola de impresion"
    exit 1
}
POWERSHELL;

        $psFile = $tempFile . '.ps1';
        file_put_contents($psFile, $script);

        $command = 'powershell -ExecutionPolicy Bypass -File ' . escapeshellarg($psFile);
        exec($command, $output, $returnVar);

        @unlink($tempFile);
        @unlink($psFile);

        if ($returnVar !== 0) {
            throw new Exception("Error al enviar a la cola de Windows: " . implode(" ", $output));
        }
    }

    public function read($len)
    {
        return false;
    }

    public function write($data)
    {
        $this->buffer[] = $data;
    }
}
