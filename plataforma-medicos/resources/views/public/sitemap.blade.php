{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc></url>
    <url><loc>{{ route('for-doctors') }}</loc></url>
@foreach ($combos as $combo)
    <url><loc>{{ route('directory', [$combo->specialty, $combo->city]) }}</loc></url>
@endforeach
@foreach ($doctors as $doctor)
    <url><loc>{{ route('doctors.show', $doctor->slug) }}</loc><lastmod>{{ $doctor->updated_at->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
