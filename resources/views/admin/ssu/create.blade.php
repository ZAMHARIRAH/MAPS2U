@extends('layouts.app', ['title' => 'Add SSU'])
@section('content')
<div class="auth-card wide"><h1>Add SSU Account</h1><form method="POST" action="{{ route('admin.ssu.store') }}">@csrf
<label>Role</label>
<div class="radio-group"><label><input type="radio" name="sub_role" value="ssu" {{ old('sub_role', 'ssu') === 'ssu' ? 'checked' : '' }}> SSU</label><label><input type="radio" name="sub_role" value="master_ssu" {{ old('sub_role') === 'master_ssu' ? 'checked' : '' }}> MASTER SSU</label></div>
<label>SSU Name</label><input type="text" name="name" value="{{ old('name') }}" required>
<label>SSU Email Address</label><input type="email" name="email" value="{{ old('email') }}" required>
<label>SSU Phone Number</label><input type="text" name="phone_number" value="{{ old('phone_number') }}" required>
<label>Request For (Branches)</label><div class="checkbox-chip-grid">@foreach($branches as $branch)<label class="inline-check"><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" {{ in_array($branch->id, old('branch_ids', [])) ? 'checked' : '' }}> {{ $branch->name }}</label>@endforeach</div>
<label>Password</label><input type="password" name="password" required><label>Confirm Password</label><input type="password" name="password_confirmation" required><div class="action-row"><button class="btn primary" type="submit">Create Account</button><a class="btn ghost" href="{{ route('admin.ssu.index') }}">Cancel</a></div></form></div>
@endsection
