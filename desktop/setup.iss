; Inno Setup 6 — Compilar: F9 en Inno Setup, o ejecutar compilar-instalador.bat
; Salida: desktop\Output\RestauranteERP-Setup.exe

#define MyAppName "Restaurante ERP"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Restaurante ERP"
#define MyAppURL "http://127.0.0.1:8080"
#define MyAppExeName "RestauranteERP.exe"

; Ruta del proyecto Laravel (carpeta padre de desktop)
#define SourcePath ".."

[Setup]
AppId={{8F4E2A91-6C3D-4B7E-9F12-A1B2C3D4E5F6}}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
DefaultDirName={autopf}\RestauranteERP
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=no
OutputDir=Output
OutputBaseFilename=RestauranteERP-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64compatible
[Languages]
Name: "spanish"; MessagesFile: "compiler:Languages\Spanish.isl"

[Tasks]
Name: "desktopicon"; Description: "Crear icono en el escritorio"; GroupDescription: "Accesos directos:"; Flags: checkedonce
Name: "firewall"; Description: "Permitir puerto 8080 en el firewall de Windows"; GroupDescription: "Red:"; Flags: checkedonce

[Files]
; Aplicacion. Incluye vendor si ya fue preparado con composer install para poder instalar sin internet.
Source: "{#SourcePath}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: ".env,node_modules\*,.git\*,tests\*,database\database.sqlite,storage\logs\*,storage\app\backups\*,storage\app\secure_reports\*,storage\app\private\inventory-csv\*,storage\framework\cache\*,storage\framework\sessions\*,storage\framework\testing\*,storage\framework\views\*,bootstrap\cache\*,desktop\Output\*,desktop\RestauranteERP.exe,desktop\Instalar-RestauranteERP.exe,desktop\_wrap-instalar.bat,.phpunit.result.cache"

; Herramientas desktop
Source: "{#SourcePath}\desktop\Iniciar-RestauranteERP.bat"; DestDir: "{app}\desktop"; Flags: ignoreversion
Source: "{#SourcePath}\desktop\Instalar-RestauranteERP.bat"; DestDir: "{app}\desktop"; Flags: ignoreversion
Source: "{#SourcePath}\desktop\Instalar-RestauranteERP.ps1"; DestDir: "{app}\desktop"; Flags: ignoreversion
Source: "{#SourcePath}\desktop\LEEME.txt"; DestDir: "{app}\desktop"; Flags: ignoreversion
Source: "{#SourcePath}\desktop\crear-instalador.ps1"; DestDir: "{app}\desktop"; Flags: ignoreversion
#ifexist "RestauranteERP.exe"
Source: "RestauranteERP.exe"; DestDir: "{app}\desktop"; Flags: ignoreversion
#endif
#ifexist "Instalar-RestauranteERP.exe"
Source: "Instalar-RestauranteERP.exe"; DestDir: "{app}\desktop"; Flags: ignoreversion
#endif

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\desktop\Iniciar-RestauranteERP.bat"; WorkingDir: "{app}"; Comment: "Iniciar servidor ERP"
Name: "{group}\Configurar / Actualizar"; Filename: "{app}\desktop\Instalar-RestauranteERP.bat"; WorkingDir: "{app}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\desktop\Iniciar-RestauranteERP.bat"; WorkingDir: "{app}"; Tasks: desktopicon
Name: "{group}\Desinstalar {#MyAppName}"; Filename: "{uninstallexe}"

[Run]
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\desktop\Instalar-RestauranteERP.ps1"" -InstallDir ""{app}"" -SkipCopy -Silent"; StatusMsg: "Configurando dependencias y base de datos..."; Flags: waituntilterminated; Check: FileExists(ExpandConstant('{app}\desktop\Instalar-RestauranteERP.ps1'))
Filename: "{app}\desktop\Iniciar-RestauranteERP.bat"; Description: "Iniciar {#MyAppName} ahora"; Flags: postinstall nowait skipifsilent unchecked

[UninstallDelete]
Type: filesandordirs; Name: "{app}\storage\logs"
Type: files; Name: "{app}\INSTALADO.txt"

[Code]
procedure CurStepChanged(CurStep: TSetupStep);
var
  ResultCode: Integer;
begin
  if (CurStep = ssPostInstall) and WizardIsTaskSelected('firewall') then
  begin
    Exec('netsh', 'advfirewall firewall add rule name="RestauranteERP-HTTP-8080" dir=in action=allow protocol=TCP localport=8080', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  end;
end;
