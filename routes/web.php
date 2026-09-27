<?php

use App\Http\Controllers\Admin\CommerceInventoryController;
use App\Http\Controllers\Admin\ModifierController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PrinterController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\StaffUserController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CookController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProgrammerController;
use App\Http\Controllers\Programmer\InventoryImportController;
use App\Http\Controllers\Programmer\PrintTemplateController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\WaiterController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/', fn() => redirect()->route('login'));
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Cocina (cocineros, solo lectura)
Route::middleware(['auth', 'role:cook,administrator,programmer'])->prefix('cook')->group(function () {
    Route::get('/recipes', [CookController::class, 'recipes'])->name('cook.recipes');
    Route::get('/recipes/{product}', [CookController::class, 'recipeDetail'])->name('cook.recipe-detail');
});

// Mesero (comandero digital)
Route::middleware(['auth', 'role:cook,administrator,programmer'])->prefix('production')->group(function () {
    Route::get('/', [\App\Http\Controllers\ProductionController::class, 'index'])->name('production.index');
    Route::post('/', [\App\Http\Controllers\ProductionController::class, 'store'])->name('production.store');
    Route::post('/{production}/complete', [\App\Http\Controllers\ProductionController::class, 'complete'])->name('production.complete');
    Route::post('/{production}/cancel', [\App\Http\Controllers\ProductionController::class, 'cancel'])->name('production.cancel');
});

Route::middleware(['auth', 'role:waiter,administrator,programmer'])->prefix('waiter')->group(function () {
    Route::get('/orders', [WaiterController::class, 'orders'])->name('waiter.orders');
    Route::get('/orders/create', [WaiterController::class, 'createOrder'])->name('waiter.create-order');
    Route::post('/orders', [WaiterController::class, 'storeOrder'])->name('waiter.store-order');
    Route::post('/orders/{order}/send-kitchen', [WaiterController::class, 'sendToKitchen'])->name('waiter.send-kitchen');
    Route::post('/orders/{order}/preticket', [WaiterController::class, 'printPreticket'])->name('waiter.print-preticket');
});

// Estado POS en tiempo real (JSON para tablets)
Route::middleware(['auth', 'role:cashier,administrator,programmer,waiter'])->group(function () {
    Route::get('/api/pos/status', [SetupController::class, 'posStatus'])->name('api.pos.status');
});

// Cajero POS
Route::middleware(['auth', 'role:cashier,administrator,programmer'])->prefix('cashier')->group(function () {
    Route::get('/pos', [PosController::class, 'pos'])->name('cashier.pos');
    Route::get('/domicilios', [WaiterController::class, 'createOrder'])->name('cashier.delivery.create');
    Route::post('/domicilios', [WaiterController::class, 'storeOrder'])->name('cashier.delivery.store');
    Route::get('/cash-count', [PosController::class, 'cashCount'])->name('cashier.cash-count');
    Route::post('/cash-count', [PosController::class, 'storeCashCount'])->name('cashier.cash-count.store');
    Route::get('/pos/table/{table}', [PosController::class, 'tableDetail'])->name('cashier.table-detail');
    Route::post('/pos/orders/{order}/ready', [PosController::class, 'markReady'])->name('cashier.mark-ready');
    Route::post('/pos/orders/{order}/send-kitchen', [PosController::class, 'sendToKitchen'])->name('cashier.send-kitchen');
    Route::post('/pos/orders/{order}/details', [PosController::class, 'storeOrderDetail'])->name('cashier.order-details.store');
    Route::put('/pos/order-details/{detail}', [PosController::class, 'updateOrderDetail'])->name('cashier.order-details.update');
    Route::post('/pos/orders/{order}/pay', [PosController::class, 'payOrder'])->name('cashier.pay-order');
    Route::post('/pos/orders/{order}/cancel', [PosController::class, 'cancelOrder'])->name('cashier.cancel-order');
    Route::post('/pos/orders/{order}/transfer', [PosController::class, 'transferOrder'])->name('cashier.transfer-order');
    Route::delete('/pos/order-details/{detail}', [PosController::class, 'destroyOrderDetail'])->name('cashier.order-details.destroy');
    Route::post('/pos/cash-closure', [PosController::class, 'closeCashierDay'])->name('cashier.cash-closure');
    Route::get('/pos/cash-closure/{closure}/pdf', [PosController::class, 'cashierClosurePdf'])->name('cashier.cash-closure.pdf');
    Route::post('/pos/report-z', [PosController::class, 'generateReportZ'])->name('cashier.report-z');
});

// Administrador
Route::middleware(['auth', 'role:administrator,programmer'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('/monthly-reports/close', [DashboardController::class, 'closeMonthlyReport'])->name('admin.monthly-reports.close');
    Route::get('/monthly-reports/compare', [DashboardController::class, 'compareReports'])->name('admin.monthly-reports.compare');
    Route::get('/monthly-reports/{report}/pdf', [DashboardController::class, 'monthlyReportPdf'])->name('admin.monthly-reports.pdf');
    Route::get('/daily-reports/{report}/pdf', [DashboardController::class, 'dailyReportPdf'])->name('admin.daily-reports.pdf');
    Route::get('/inventory', [CommerceInventoryController::class, 'index'])->name('admin.inventory.index');
    Route::post('/inventory', [CommerceInventoryController::class, 'store'])->name('admin.inventory.store');
    Route::post('/inventory/{supply}/adjust', [CommerceInventoryController::class, 'adjust'])->name('admin.inventory.adjust');
    Route::put('/inventory/{supply}', [CommerceInventoryController::class, 'update'])->name('admin.inventory.update');
    Route::delete('/inventory/{supply}', [CommerceInventoryController::class, 'destroy'])->name('admin.inventory.destroy');
    Route::post('/inventory/purchases', [CommerceInventoryController::class, 'storePurchase'])->name('admin.inventory.purchase');
    Route::get('/supplies', [DashboardController::class, 'supplies'])->name('admin.supplies');
    Route::post('/supplies', [DashboardController::class, 'storeSupply'])->name('admin.store-supply');
    Route::post('/supplies/{supply}/purchase', [DashboardController::class, 'registerPurchase'])->name('admin.purchase');
    Route::post('/supplies/{supply}/waste', [DashboardController::class, 'registerWaste'])->name('admin.waste');
    Route::get('/physical-inventory', [DashboardController::class, 'createPhysicalInventory'])->name('admin.physical-inventory');
    Route::post('/physical-inventory', [DashboardController::class, 'storePhysicalInventory'])->name('admin.store-inventory');
    Route::get('/physical-inventory/{inventory}/results', [DashboardController::class, 'inventoryResults'])->name('admin.inventory-results');

    Route::get('/tables', [TableController::class, 'index'])->name('admin.tables');
    Route::post('/tables', [TableController::class, 'store'])->name('admin.tables.store');
    Route::put('/tables/{table}', [TableController::class, 'update'])->name('admin.tables.update');
    Route::delete('/tables/{table}', [TableController::class, 'destroy'])->name('admin.tables.destroy');

    Route::get('/products', [ProductController::class, 'index'])->name('admin.products');
    Route::post('/products', [ProductController::class, 'store'])->name('admin.products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('admin.products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('admin.products.destroy');

    Route::get('/staff', [StaffUserController::class, 'index'])->name('admin.staff.index');
    Route::get('/audit', [\App\Http\Controllers\Admin\AuditController::class, 'index'])->name('admin.audit');
    Route::post('/staff', [StaffUserController::class, 'store'])->name('admin.staff.store');
    Route::put('/staff/{user}', [StaffUserController::class, 'update'])->name('admin.staff.update');
    Route::delete('/staff/{user}', [StaffUserController::class, 'destroy'])->name('admin.staff.destroy');

    Route::get('/printers', [PrinterController::class, 'index'])->name('admin.printers');
    Route::post('/printers', [PrinterController::class, 'store'])->name('admin.printers.store');
    Route::put('/printers/{printer}', [PrinterController::class, 'update'])->name('admin.printers.update');
    Route::delete('/printers/{printer}', [PrinterController::class, 'destroy'])->name('admin.printers.destroy');
    Route::post('/printers/{printer}/test', [PrinterController::class, 'test'])->name('admin.printers.test');

    // Opciones/modificadores de platos
    Route::get('/modifiers', [ModifierController::class, 'index'])->name('admin.modifiers.index');
    Route::post('/modifiers', [ModifierController::class, 'store'])->name('admin.modifiers.store');
    Route::put('/modifiers/{modifier}', [ModifierController::class, 'update'])->name('admin.modifiers.update');
    Route::delete('/modifiers/{modifier}', [ModifierController::class, 'destroy'])->name('admin.modifiers.destroy');
    Route::patch('/modifiers/{modifier}/toggle', [ModifierController::class, 'toggleActive'])->name('admin.modifiers.toggle');
    Route::post('/modifiers/sync-category/{category}', [ModifierController::class, 'syncCategory'])->name('admin.modifiers.sync-category');
    Route::patch('/modifiers/toggle-category/{category}/{modifier}', [ModifierController::class, 'toggleCategoryOption'])->name('admin.modifiers.toggle-category');
    Route::post('/modifiers/sync-product/{product}', [ModifierController::class, 'syncProduct'])->name('admin.modifiers.sync-product');
    Route::delete('/modifiers/clear-product/{product}', [ModifierController::class, 'clearProductOverride'])->name('admin.modifiers.clear-product');
});

// Red LAN, accesible para roles operativos
Route::middleware(['auth', 'role:administrator,programmer,cashier,waiter'])->group(function () {
    Route::get('/setup/network', [SetupController::class, 'network'])->name('admin.network');
});

// Programador
Route::middleware(['auth', 'role:programmer'])->prefix('programmer')->group(function () {
    Route::get('/inventory-import', [InventoryImportController::class, 'show'])->name('programmer.inventory-import');
    Route::post('/inventory-import/upload', [InventoryImportController::class, 'upload'])->name('programmer.inventory-import.upload');
    Route::post('/inventory-import', [InventoryImportController::class, 'import'])->name('programmer.inventory-import.store');
    Route::post('/inventory-import/{upload}/import', [InventoryImportController::class, 'importStored'])->name('programmer.inventory-import.import-stored');
    Route::delete('/inventory-import/{upload}', [InventoryImportController::class, 'destroy'])->name('programmer.inventory-import.destroy');
    Route::get('/inventory-import/plantilla.csv', [InventoryImportController::class, 'downloadTemplate'])->name('programmer.inventory-import.template');

    Route::get('/panel', [ProgrammerController::class, 'panel'])->name('programmer.panel');
    Route::post('/purge', [ProgrammerController::class, 'purge'])->name('programmer.purge');
    Route::post('/kill-processes', [ProgrammerController::class, 'killProcesses'])->name('programmer.kill-processes');
    Route::post('/repair-integrity', [ProgrammerController::class, 'repairIntegrity'])->name('programmer.repair-integrity');
    Route::get('/integrity-check', [ProgrammerController::class, 'integrityDryRun'])->name('programmer.integrity-check');

    Route::get('/print-templates', [PrintTemplateController::class, 'index'])->name('programmer.print-templates.index');
    Route::get('/print-templates/{slug}', [PrintTemplateController::class, 'edit'])->name('programmer.print-templates.edit');
    Route::put('/print-templates/{slug}', [PrintTemplateController::class, 'update'])->name('programmer.print-templates.update');
});
