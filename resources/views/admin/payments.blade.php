@extends('layouts.admin')

@section('content')
    <!--start page wrapper -->
    <div class="page-wrapper">
        <div class="page-content">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4">
                <div class="col">
                    <div class="card radius-10">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div>
                                    <p class="mb-0 text-secondary">{{ trans('messages.total_users') }}</p>
                                    <h4 class="my-1">45,805</h4>
                                </div>
                                <div class="widgets-icons bg-light-success text-success ms-auto"><i
                                        class="bx bxs-wallet"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div>
                                    <p class="mb-0 text-secondary">{{ trans('messages.total_tasks') }}</p>
                                    <h4 class="my-1">12450</h4>

                                </div>
                                <div class="widgets-icons bg-light-info text-info ms-auto"><i class='bx bxs-group'></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div>
                                    <p class="mb-0 text-secondary">{{ trans('messages.gig_workers') }}</p>
                                    <h4 class="my-1">5,953</h4>

                                </div>
                                <div class="widgets-icons bg-light-danger text-danger ms-auto"><i
                                        class='bx bxs-binoculars'></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div>
                                    <p class="mb-0 text-secondary">{{ trans('messages.revenue') }}</p>
                                    <h4 class="my-1">$3446</h4>

                                </div>
                                <div class="widgets-icons bg-light-warning text-warning ms-auto"><i
                                        class='bx bx-line-chart-down'></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-lg-12 d-flex">
                    <div class="card radius-10 w-100">
                        <div class="card-header">

                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center ms-auto font-13 gap-2 mb-3">
                                <h4 class="mb-0">{{ trans('messages.total_active_users') }}</h4>
                                <br>

                                <h5 class="mb-0">12,6523.6</h5>
                                <span class="border px-1 rounded cursor-pointer" onclick="showAll()">
                                    <i class="bx bxs-circle me-1" style="color: #14ef68"></i>{{ trans('messages.all') }}
                                </span>
                                <span class="border px-1 rounded cursor-pointer" onclick="showMobile()">
                                    <i class="bx bxs-circle me-1" style="color: #14abef"></i>{{ trans('messages.mobile') }}
                                </span>
                                <span class="border px-1 rounded cursor-pointer" onclick="showDesktop()">
                                    <i class="bx bxs-circle me-1" style="color: #ffc107"></i>{{ trans('messages.desktop') }}
                                </span>

                            </div>
                            <div class="chart-container-1">
                                <canvas id="chart1" height="120"></canvas>
                            </div>
                        </div>
                        <div class="row row-cols-1 row-cols-md-3 row-cols-xl-3 g-0 row-group text-center border-top">

                        </div>
                    </div>
                </div>
                {{-- <div class="col-12 col-lg-4 d-flex">
                       <div class="card radius-10 w-100">
						<div class="card-header">
							<div class="d-flex align-items-center">
								<div>
									<h6 class="mb-0">Trending Products</h6>
								</div>
								<div class="dropdown ms-auto">
									<a class="dropdown-toggle dropdown-toggle-nocaret" href="#" data-bs-toggle="dropdown"><i class='bx bx-dots-horizontal-rounded font-22 text-option'></i>
									</a>
									<ul class="dropdown-menu">
										<li><a class="dropdown-item" href="javascript:;">Action</a>
										</li>
										<li><a class="dropdown-item" href="javascript:;">Another action</a>
										</li>
										<li>
											<hr class="dropdown-divider">
										</li>
										<li><a class="dropdown-item" href="javascript:;">Something else here</a>
										</li>
									</ul>
								</div>
							</div>
						</div>
						   <div class="card-body">
							<div class="chart-container-2">
								<canvas id="chart2"></canvas>
							  </div>
						   </div>
						   <ul class="list-group list-group-flush">
							<li class="list-group-item d-flex bg-transparent justify-content-between align-items-center border-top">Jeans <span class="badge bg-success rounded-pill">25</span>
							</li>
							<li class="list-group-item d-flex bg-transparent justify-content-between align-items-center">T-Shirts <span class="badge bg-danger rounded-pill">10</span>
							</li>
							<li class="list-group-item d-flex bg-transparent justify-content-between align-items-center">Shoes <span class="badge bg-primary rounded-pill">65</span>
							</li>
							<li class="list-group-item d-flex bg-transparent justify-content-between align-items-center">Lingerie <span class="badge bg-warning text-dark rounded-pill">14</span>
							</li>
						</ul>
					   </div>
				   </div> --}}
            </div><!--end row-->

            <div class="card radius-10">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0">{{ trans('messages.recent_user_activities') }}</h6>
                        </div>

                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ trans('messages.users') }}</th>
                                    <th>{{ trans('messages.activity') }}</th>
                                    <th>{{ trans('messages.time') }}</th>
                                    <th>{{ trans('messages.status') }}</th>
                                    <th>{{ trans('messages.date') }}</th>

                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Johan</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-blooker text-white shadow-sm w-10">{{ trans('messages.pending') }}</span>
                                    </td>
                                    <td>03 Feb 2020</td>

                                </tr>
                                <tr>
                                    <td>Herry</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-quepal text-white shadow-sm w-10">{{ trans('messages.active') }}</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Johan</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-blooker text-white shadow-sm w-10">Pending</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Herry</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-quepal text-white shadow-sm w-10">Active</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Johan</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-blooker text-white shadow-sm w-10">Pending</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Herry</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-quepal text-white shadow-sm w-10">Active</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Johan</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-blooker text-white shadow-sm w-10">Pending</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Herry</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-quepal text-white shadow-sm w-10">Active</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Johan</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-blooker text-white shadow-sm w-10">Pending</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Herry</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-quepal text-white shadow-sm w-10">Active</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Herry</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-quepal text-white shadow-sm w-10">Active</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Herry</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-quepal text-white shadow-sm w-10">Active</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                                <tr>
                                    <td>Johan</td>
                                    <td>xyz</td>
                                    <td>10 mins ago</td>
                                    <td><span class="badge bg-gradient-blooker text-white shadow-sm w-10">Pending</span>
                                    </td>
                                    <td>03 Feb 2020</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end page wrapper -->
@endsection
