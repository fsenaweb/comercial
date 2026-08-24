@include('reports.partials.head', ['report' => $report, 'letterhead' => $letterhead, 'autoPrint' => $autoPrint ?? false])
@if ($report['rows'] === [])
    @include('reports.partials.empty-row', ['report' => $report])
@else
    @include('reports.partials.rows', ['rows' => $report['rows'], 'headers' => $report['headers']])
@endif
@include('reports.partials.foot', ['autoPrint' => $autoPrint ?? false])
