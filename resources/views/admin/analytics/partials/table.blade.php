<div class="card radius-10 w-100">
    <div class="card-header"><h6 class="mb-0">{{ $title }}</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0" id="{{ $id }}">
                <thead class="table-light">
                    <tr>
                        @foreach ($columns as $i => $column)
                            <th class="{{ $i ? 'text-end' : '' }}">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
