@if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="flash danger"><strong>Please check the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
