<a href="{{ route('doctors.show', $doctor) }}" class="card flex gap-4 hover:border-brand-500 transition">
    @if ($doctor->photoUrl())
        <img src="{{ $doctor->photoUrl() }}" alt="{{ $doctor->displayName() }}" class="w-20 h-20 rounded-full object-cover" loading="lazy">
    @else
        <div class="w-20 h-20 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-2xl font-bold">{{ mb_substr($doctor->name, 0, 1) }}</div>
    @endif
    <div class="min-w-0">
        <h3 class="font-semibold text-lg">{{ $doctor->displayName() }}</h3>
        <p class="text-sm text-slate-600">{{ $doctor->specialty->name }} · {{ $doctor->city->name }}</p>
        @if ($doctor->rating_count)
            <p class="text-sm mt-1">@include('partials.stars', ['rating' => $doctor->rating_avg]) <span class="text-slate-500">({{ $doctor->rating_count }})</span></p>
        @endif
        @if ($doctor->consultation_price_mxn)
            <p class="text-sm text-slate-500 mt-1">Consulta desde ${{ number_format($doctor->consultation_price_mxn) }}</p>
        @endif
    </div>
</a>
