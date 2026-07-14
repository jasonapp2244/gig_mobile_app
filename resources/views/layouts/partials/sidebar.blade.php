<!--sidebar wrapper -->
<div class="sidebar-wrapper" data-simplebar="true">
    <div class="sidebar-header">
        <div>
            <img src="{{ asset('admin/images/logo-icon.png') }}" class="logo-icon" alt="logo icon">
        </div>
        <div>
            <h4 class="logo-text">{{ trans('messages.app_name') }}</h4>
        </div>
        <div class="toggle-icon ms-auto">
            <i class='bx bx-arrow-back'></i>
        </div>
    </div>

    <!--navigation-->
    <ul class="metismenu" id="menu">

        <!-- Dashboard -->
        <li>
            <a href="{{ route('admin.dashboard') }}">
                <div class="parent-icon"><i class='bx bx-home'></i></div>
                <div class="menu-title">{{ trans('messages.dashboard_overview') }}</div>
            </a>
        </li>

        <!-- User Management -->
        <li>
            <a href="javascript:;" class="has-arrow">
                <div class="parent-icon"><i class='bx bx-user'></i></div>
                <div class="menu-title">{{ trans('messages.user_management') }}</div>
            </a>
            <ul>
                <li>
                    <a href="{{ route('admin.users') }}">
                        <i class='bx bx-radio-circle'></i>{{ trans('messages.users_records') }}
                    </a>
                </li>
            </ul>
        </li>

        <!-- Job Monitoring -->
        <li>
            <a href="javascript:void(0);" class="has-arrow" aria-expanded="false">
                <div class="parent-icon"><i class='bx bx-briefcase'></i></div>
                <div class="menu-title">{{ trans('messages.job_monitoring') }}</div>
            </a>
            <ul>
                <li>
                    <a href="{{ route('admin.jobMonitoring') }}">
                        <i class='bx bx-radio-circle'></i> {{ trans('messages.job_records') }}
                    </a>
                </li>
            </ul>
        </li>

        <!-- Category Management -->
        <li>
            <a href="javascript:void(0);" class="has-arrow" aria-expanded="false">
                <div class="parent-icon"><i class='bx bx-category'></i></div>
                <div class="menu-title">{{ trans('messages.category') }}</div>
            </a>
            <ul>
                <li>
                    <a href="{{ route('admin.categories') }}">
                        <i class='bx bx-radio-circle'></i> {{ trans('messages.category_records') }}
                    </a>
                </li>
            </ul>
        </li>

           <li>
            <a href="javascript:void(0);" class="has-arrow" aria-expanded="false">
                <div class="parent-icon"><i class='bx bx-list-ul'></i></div>
                <div class="menu-title">List Management</div>
            </a>
            <ul>
                <li>
                    <a href="{{ route('admin.list.index') }}">
                        <i class='bx bx-radio-circle'></i> All Lists
                    </a>
                </li>
            </ul>
        </li>

        <!-- Support Tickets -->
        <li>
            <a href="javascript:;" class="has-arrow">
                <div class="parent-icon"><i class='bx bx-support'></i></div>
                <div class="menu-title">{{ trans('messages.support_tickets') }}</div>
            </a>
            <ul>
                <li>
                    <a href="{{ route('support.index') }}">
                        <i class='bx bx-radio-circle'></i>{{ trans('messages.support_tickets_records') }}
                    </a>
                </li>
            </ul>
        </li>

        <!-- Privacy Policy -->
        <li>
            <a href="{{ route('admin.privacy-policy.index') }}">
                <div class="parent-icon"><i class='bx bx-shield'></i></div>
                <div class="menu-title">Privacy Policy</div>
            </a>
        </li>

        <!-- Settings -->
        <li>
            <a href="javascript:;" class="has-arrow">
                <div class="parent-icon"><i class='bx bx-cog'></i></div>
                <div class="menu-title">{{ trans('messages.settings') }}</div>
            </a>
            <ul>
                <li>
                    <a href="{{ route('setting.view.profile') }}">
                        <i class='bx bx-radio-circle'></i>Account Setting
                    </a>
                </li>
  <li>
                    <a href="{{ route('setting.change.password') }}">
                        <i class='bx bx-radio-circle'></i>Change Password
                    </a>
                </li>
            </ul>
        </li>

        <!-- Logout -->
        <li>
            <a href="{{ route('logout') }}">
                <div class="parent-icon"><i class='bx bx-log-out'></i></div>
                <div class="menu-title">{{ trans('messages.logout') }}</div>
            </a>
        </li>

    </ul>
    <!--end navigation-->

</div>
<!--end sidebar wrapper -->
