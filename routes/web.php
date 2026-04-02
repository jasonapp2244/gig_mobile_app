<?php

use App\Http\Controllers\Admin\{
    AdminPaymentController,
    DashboardController,
    AdminUserController,
    AdminAuthController,
    AdminCategoryController,
    AdminJobMonitoringController,
    AdminSettingController,
    AdminSupportController,
    AdminListController
};

use Illuminate\Support\Facades\Route;

use App\Http\Middleware\SetLocale;

Route::middleware([SetLocale::class])->group(function () {

    Route::get('/login', [AdminAuthController::class, 'index'])->name('admin.login');

    Route::post('/login', [AdminAuthController::class, 'signin'])->name('admin.signin');
    Route::middleware(['auth:admin', 'admin', 'setAdminTimezone'])->group(function () {
        // Route::post('/change-language', [\App\Http\Controllers\LanguageController::class, 'change'])->name('change.language');
        //chart
        Route::get('/admin/chart-data', [App\Http\Controllers\Admin\DashboardController::class, 'getChartData'])->name('admin.chart.data');

        // AJAX ke liye recent activities only
        Route::get('/admin/dashboard', [DashboardController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/admin/recent-activities', [DashboardController::class, 'getRecentActivitiesAjax'])->name('admin.recent.activities');
        //for date all user record
        Route::get('/admin/dashboard/data', [DashboardController::class, 'getDashboardDataAjax'])->name('admin.dashboard.data');

        //User Management
        Route::get('/admin/users', [AdminUserController::class, 'users'])->name('admin.users'); // normal blade
        Route::get('/admin/fetch-users', [AdminUserController::class, 'fetchUsers'])->name('admin.fetch.users'); // ajax

        // show edit form
        Route::get('/admin/user/{id}', [AdminUserController::class, 'edit'])->name('admin.users.edit');
        Route::post('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');

        //
        Route::get('/view-user/{id}', [AdminUserController::class, 'viewUser'])->name('admin.viewUser');
        Route::get('/view-user/{id}', [AdminUserController::class, 'viewUser'])->name('admin.viewUser');

        //Category Management
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('admin.categories');
        Route::post('/categories/store', [AdminCategoryController::class, 'store'])->name('admin.categories.store');
        Route::get('/categories/edit/{id}', [AdminCategoryController::class, 'edit'])->name('admin.categories.edit');
        Route::post('/categories/update/{id}', [AdminCategoryController::class, 'update'])->name('admin.categories.update');
        Route::delete('/categories/delete/{id}', [AdminCategoryController::class, 'destroy'])->name('admin.categories.delete');


        //Job Monitoring
        Route::get('/admin/jobs/{id}', [AdminJobMonitoringController::class, 'show'])->name('admin.jobs.show');
        Route::get('/job-monitoring', [AdminJobMonitoringController::class, 'index'])->name('admin.jobMonitoring');

        // JSON fetch route (sirf AJAX ke liye)
        Route::get('/job-monitoring-fetch', [AdminJobMonitoringController::class, 'fetchJobs'])->name('admin.jobs.fetch');

        //Support
        Route::get('/support', [AdminSupportController::class, 'index'])->name('support.index');
        Route::post('/support/{id}/respond', [AdminSupportController::class, 'respond'])->name('support.respond');
        // normal page

        // ajax fetch (used by JS)
        Route::get('/admin/fetch-supports', [AdminSupportController::class, 'fetchSupports'])->name('admin.fetchSupports');
        // show single (already used in your view links)
        Route::get('/support/{id}', [AdminSupportController::class, 'show'])->name('support.show');

        // List Management
        Route::get('/admin/lists',                      [AdminListController::class, 'index'])->name('admin.list.index');
        Route::get('/admin/lists/fetch',                [AdminListController::class, 'fetchLists'])->name('admin.list.fetch');
        Route::get('/admin/list/create',                [AdminListController::class, 'create'])->name('admin.list.create');
        Route::post('/admin/lists',                     [AdminListController::class, 'store'])->name('admin.list.store');
        Route::get('/admin/list/{id}',                  [AdminListController::class, 'show'])->name('admin.list.show');
        Route::get('/admin/list/{id}/edit',             [AdminListController::class, 'edit'])->name('admin.list.edit');
        Route::put('/admin/list/{id}',                  [AdminListController::class, 'update'])->name('admin.list.update');
        Route::delete('/admin/list/{id}',               [AdminListController::class, 'destroy'])->name('admin.list.destroy');
        Route::post('/admin/list/{id}/toggle-status',   [AdminListController::class, 'toggleStatus'])->name('admin.list.toggleStatus');
        Route::delete('/admin/list/image/{imageId}',    [AdminListController::class, 'destroyImage'])->name('admin.list.image.destroy');

        //Admin Settings
        Route::get('/setting/view-profile', [AdminSettingController::class, 'settingAdminProfile'])->name('setting.view.profile');
        Route::get('/setting/edit-profile/{id}', [AdminSettingController::class, 'editProfile'])->name('setting.edit.profile');
        Route::post('/setting/update-profile', [AdminSettingController::class, 'updateProfile'])->name('setting.update.profile');

        // Change Password
        Route::get('/setting/change-password', [AdminSettingController::class, 'changePasswordForm'])->name('setting.change.password');
        Route::post('/setting/change-password', [AdminSettingController::class, 'changePassword'])->name('setting.change.password.update');

        // Route::get('/payments', [AdminPaymentController::class, 'payments'])->name('admin.payments');
        Route::get('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});

Route::fallback(function () {
   return redirect()->route('admin.login');
});
