{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $u)
  <url><loc>{{ $u['loc'] }}</loc>@isset($u['lastmod'])<lastmod>{{ $u['lastmod'] }}</lastmod>@endisset @isset($u['changefreq'])<changefreq>{{ $u['changefreq'] }}</changefreq>@endisset<priority>{{ $u['priority'] }}</priority></url>
@endforeach
</urlset>
