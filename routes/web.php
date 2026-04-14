<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ClientAccountController;
use App\Http\Controllers\Admin\ClientRequestController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\SsuController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\RequestTypeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TechnicianController;
use App\Http\Controllers\Admin\TaskTitleController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Client\RequestController as ClientRequestFormController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TechnicianDashboardController;
use App\Http\Controllers\TechnicianRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->name('admin.login.submit');
    Route::get('/technician/login', [AuthController::class, 'showTechnicianLogin'])->name('technician.login');
    Route::post('/technician/login', [AuthController::class, 'technicianLogin'])->name('technician.login.submit');
    Route::get('/client/login', [AuthController::class, 'showClientLogin'])->name('client.login');
    Route::post('/client/login', [AuthController::class, 'clientLogin'])->name('client.login.submit');
    Route::get('/client/register', [AuthController::class, 'showClientRegister'])->name('client.register');
    Route::post('/client/register', [AuthController::class, 'clientRegister'])->name('client.register.submit');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.forgot');
    Route::post('/forgot-password', [AuthController::class, 'sendResetPasswordLink'])->name('password.email');
    Route::get('/reset-password', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    Route::get('/login/{role}', [AuthController::class, 'redirectLoginByRole'])->name('login.by.role');
});

Route::middleware('auth')->get('/files/{encodedPath}', [FileController::class, 'show'])->where('encodedPath', '.*')->name('files.show');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/maps/dashboard', [AdminDashboardController::class, 'mapsDashboard'])->name('maps.dashboard');
    Route::get('/accounts', [AdminDashboardController::class, 'accounts'])->name('accounts.index');
    Route::get('/clients/{client}', [ClientAccountController::class, 'show'])->name('clients.show');
    Route::resource('technicians', TechnicianController::class);
    Route::get('/ssu', [SsuController::class, 'index'])->name('ssu.index');
    Route::get('/ssu/create', [SsuController::class, 'create'])->name('ssu.create');
    Route::post('/ssu', [SsuController::class, 'store'])->name('ssu.store');
    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::get('/vendors/create', [VendorController::class, 'create'])->name('vendors.create');
    Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
    Route::get('/vendors/{vendor}/edit', [VendorController::class, 'edit'])->name('vendors.edit');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
    Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');

    Route::get('/locations/{type}', [LocationController::class, 'index'])->name('locations.index');
    Route::get('/locations/{type}/create', [LocationController::class, 'create'])->name('locations.create');
    Route::post('/locations/{type}', [LocationController::class, 'store'])->name('locations.store');
    Route::get('/location-records/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
    Route::put('/location-records/{location}', [LocationController::class, 'update'])->name('locations.update');
    Route::delete('/location-records/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');

    Route::resource('departments', DepartmentController::class)->except(['show']);
    Route::resource('tasks', TaskTitleController::class)->except(['show']);
    Route::resource('announcements', AnnouncementController::class)->except(['show', 'destroy']);
    Route::patch('/announcements/{announcement}/toggle', [AnnouncementController::class, 'toggle'])->name('announcements.toggle');
    Route::resource('request-types', RequestTypeController::class);
    Route::get('/incoming-requests', [ClientRequestController::class, 'index'])->name('incoming-requests.index');
    Route::get('/incoming-requests/print-filtered', [ClientRequestController::class, 'filteredPrint'])->name('incoming-requests.print-filtered');
    Route::get('/incoming-requests/{clientRequest}/print', [ClientRequestController::class, 'print'])->name('incoming-requests.print');
    Route::get('/incoming-requests/{clientRequest}', [ClientRequestController::class, 'show'])->name('incoming-requests.show');
    Route::post('/incoming-requests/{clientRequest}/decision', [ClientRequestController::class, 'reviewDecision'])->name('incoming-requests.decision');
    Route::post('/incoming-requests/{clientRequest}/subject-approval-toggle', [ClientRequestController::class, 'toggleSubjectApproval'])->name('incoming-requests.subject-approval-toggle');
    Route::post('/incoming-requests/{clientRequest}/technician-review-remark', [ClientRequestController::class, 'appendTechnicianReviewRemark'])->name('incoming-requests.technician-review-remark');
    Route::post('/incoming-requests/{clientRequest}/viewer-summary', [ClientRequestController::class, 'saveViewerSummary'])->name('incoming-requests.viewer-summary');
    Route::post('/incoming-requests/{clientRequest}/assign', [ClientRequestController::class, 'assign'])->name('incoming-requests.assign');
    Route::put('/incoming-requests/{clientRequest}/review', [ClientRequestController::class, 'updateReview'])->name('incoming-requests.review');
    Route::post('/incoming-requests/{clientRequest}/approve-quotation', [ClientRequestController::class, 'approveQuotation'])->name('incoming-requests.approve-quotation');
    Route::post('/incoming-requests/{clientRequest}/return-quotation', [ClientRequestController::class, 'returnQuotation'])->name('incoming-requests.return-quotation');

    Route::get('/reports/job-request', [ReportController::class, 'jobRequests'])->name('reports.job-request');
    Route::get('/reports/locations', [ReportController::class, 'locationStatistics'])->name('reports.locations');
    Route::get('/reports/locations/print-documents', [ReportController::class, 'locationDocumentsPrint'])->name('reports.locations.documents');
    Route::get('/reports/locations/archive', [ReportController::class, 'locationArchiveIndex'])->name('reports.locations.archive');
    Route::get('/reports/locations/archive/{year}', [ReportController::class, 'locationArchiveShow'])->name('reports.locations.archive.show');
    Route::get('/reports/branches', [ReportController::class, 'branchStatistics'])->name('reports.branches');
    Route::get('/reports/branches/print-documents', [ReportController::class, 'branchDocumentsPrint'])->name('reports.branches.documents');
    Route::get('/reports/branches/archive', [ReportController::class, 'branchArchiveIndex'])->name('reports.branches.archive');
    Route::get('/reports/branches/archive/{year}', [ReportController::class, 'branchArchiveShow'])->name('reports.branches.archive.show');
    Route::get('/reports/technician', [ReportController::class, 'technicians'])->name('reports.technician');
    Route::get('/reports/technician/merged-documents', [ReportController::class, 'mergedTechnicianDocuments'])->name('reports.technician.merged');
    Route::get('/reports/technician/{clientRequest}/job-report', [ReportController::class, 'technicianJobReport'])->name('reports.technician.job-report');
    Route::get('/reports/technician/{clientRequest}/feedback-report', [ReportController::class, 'clientFeedbackReport'])->name('reports.technician.feedback-report');

    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/{clientRequest}', [FinanceController::class, 'show'])->name('finance.show');
    Route::post('/finance/{clientRequest}', [FinanceController::class, 'store'])->name('finance.store');
    Route::get('/maps/finance', [FinanceController::class, 'mapsIndex'])->name('maps.finance.index');
    Route::get('/maps/finance/{clientRequest}', [FinanceController::class, 'mapsShow'])->name('maps.finance.show');
    Route::post('/maps/finance/{clientRequest}', [FinanceController::class, 'mapsStore'])->name('maps.finance.store');
});

Route::prefix('technician')->name('technician.')->middleware(['auth', 'role:technician'])->group(function () {
    Route::get('/dashboard', [TechnicianDashboardController::class, 'index'])->name('dashboard');
    Route::get('/job-requests', [TechnicianRequestController::class, 'index'])->name('job-requests.index');
    Route::get('/job-requests/{clientRequest}', [TechnicianRequestController::class, 'show'])->name('job-requests.show');
    Route::get('/job-requests/{clientRequest}/report', [TechnicianRequestController::class, 'report'])->name('job-requests.report');
    Route::post('/job-requests/{clientRequest}/return', [TechnicianRequestController::class, 'returnToClient'])->name('job-requests.return');
    Route::put('/job-requests/{clientRequest}/review', [TechnicianRequestController::class, 'saveReview'])->name('job-requests.review');
    Route::post('/job-requests/{clientRequest}/review-remark', [TechnicianRequestController::class, 'appendReviewRemark'])->name('job-requests.review-remark');
    Route::post('/job-requests/{clientRequest}/costing', [TechnicianRequestController::class, 'submitCosting'])->name('job-requests.costing');
    Route::post('/job-requests/{clientRequest}/quotation', [TechnicianRequestController::class, 'submitQuotation'])->name('job-requests.quotation');
    Route::put('/job-requests/{clientRequest}/work', [TechnicianRequestController::class, 'saveWorkExecution'])->name('job-requests.work');
    Route::post('/job-requests/{clientRequest}/inspection-timer', [TechnicianRequestController::class, 'updateInspectionTimer'])->name('job-requests.inspection-timer');
    Route::put('/job-requests/{clientRequest}/inspect', [TechnicianRequestController::class, 'saveInspectForm'])->name('job-requests.inspect');
    Route::post('/job-requests/{clientRequest}/invoice', [TechnicianRequestController::class, 'uploadInvoice'])->name('job-requests.invoice');
    Route::post('/job-requests/{clientRequest}/customer-service', [TechnicianRequestController::class, 'submitCustomerService'])->name('job-requests.customer-service');
});

Route::prefix('client')->name('client.')->middleware(['auth', 'role:client'])->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
    Route::get('/requests', [ClientRequestFormController::class, 'index'])->name('requests.index');
    Route::get('/requests/{clientRequest}', [ClientRequestFormController::class, 'show'])->name('requests.show');
    Route::post('/requests', [ClientRequestFormController::class, 'store'])->name('requests.store');
    Route::put('/requests/{clientRequest}', [ClientRequestFormController::class, 'update'])->name('requests.update');
    Route::put('/requests/{clientRequest}/feedback', [ClientRequestFormController::class, 'submitFeedback'])->name('requests.feedback');
    Route::get('/reports', [ClientRequestFormController::class, 'reportIndex'])->name('reports.index');
});
