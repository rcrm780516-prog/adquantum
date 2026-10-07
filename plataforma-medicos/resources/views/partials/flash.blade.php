@if (session('status'))
    <div class="max-w-6xl mx-auto px-4 mt-4">
        <div class="rounded-lg bg-brand-50 border border-brand-100 text-brand-900 px-4 py-3 text-sm">{{ session('status') }}</div>
    </div>
@endif
@if ($errors->any())
    <div class="max-w-6xl mx-auto px-4 mt-4">
        <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    </div>
@endif
