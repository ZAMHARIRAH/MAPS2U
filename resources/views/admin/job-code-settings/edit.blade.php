@extends('layouts.app', ['title' => 'Job Request Code'])
@section('content')
<div class="page-header"><div><h1>Job Request Code</h1><p>Set the next code used for new client requests. Imported legacy request_code is kept as-is and will not control this counter.</p></div></div>
<section class="panel">
    <form method="POST" action="{{ route('admin.job-code-settings.update') }}" class="form-grid">
        @csrf @method('PUT')
        <div class="form-group"><label>Next Job Request Code</label><input type="text" name="next_job_request_code" value="{{ old('next_job_request_code', $currentCode) }}" placeholder="W019" required><small>Example: W019. Next client request will use W019, then W020, W021 and so on.</small></div>
        <div class="full actions-inline"><button class="btn primary" type="submit">Save Code</button></div>
    </form>
</section>
@endsection
