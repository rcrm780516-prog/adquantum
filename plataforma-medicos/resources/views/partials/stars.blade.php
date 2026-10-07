<span class="text-amber-500" aria-label="{{ number_format($rating, 1) }} de 5">
    @for ($i = 1; $i <= 5; $i++){{ $i <= round($rating) ? '★' : '☆' }}@endfor
</span>
