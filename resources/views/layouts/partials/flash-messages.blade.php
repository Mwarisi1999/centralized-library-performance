@if(session('success') || session('status'))
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800" role="status">
        {{ session('success') ?? session('status') }}
    </div>
@endif

@if($errors->has('invitation'))
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700" role="alert">
        {{ $errors->first('invitation') }}
    </div>
@endif
