<form method="POST" action="{{ route('client.register.submit') }}" class="stacked-form-tight">
    @csrf
    <label>Client Name</label>
    <input type="text" name="name" value="{{ old('name') }}" required>
    <label>Client Email</label>
    <input type="email" name="email" value="{{ old('email') }}" required>
    <label>Client Phone Number</label>
    <input type="text" name="phone_number" value="{{ old('phone_number') }}" required>
    <label>Client Address</label>
    <textarea name="address" required>{{ old('address') }}</textarea>
    <label>Role</label>
    <div class="radio-card-grid">
        <label class="radio-card"><input type="radio" name="sub_role" value="hq_staff" {{ old('sub_role') === 'hq_staff' ? 'checked' : '' }} required><span>HQ Staff</span></label>
        <label class="radio-card"><input type="radio" name="sub_role" value="kindergarten" {{ old('sub_role') === 'kindergarten' ? 'checked' : '' }}><span>Kindergarten</span></label>
    </div>
    <label>Password</label>
    <input type="password" name="password" required>
    <label>Confirmation Password</label>
    <input type="password" name="password_confirmation" required>
    <button class="btn accent block" type="submit">Register</button>
</form>
