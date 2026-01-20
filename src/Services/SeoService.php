<?php

namespace App\Services;

use App\Models\SeoMetadata;
use App\Models\Shop;
use Psr\Http\Message\ServerRequestInterface;

class SeoService
{
    public function buildForShop(Shop $shop, ServerRequestInterface $request): array
    {
        $metadata = SeoMetadata::where('entity_type', 'shop')
            ->where('entity_id', $shop->id)
            ->first();

        $title = $metadata?->seo_title ?: (string)($shop->shop_name ?? '');
        $description = $metadata?->seo_description ?: (string)($shop->shop_description ?? '');
        $canonicalUrl = $metadata?->canonical_url ?: $this->buildCanonicalUrl($request);
        $ogTitle = $metadata?->og_title ?: $title;
        $ogDescription = $metadata?->og_description ?: $description;

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $metadata?->seo_keywords,
            'canonical_url' => $canonicalUrl,
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_image' => $metadata?->og_image,
            'schema_json' => $metadata?->schema_json,
        ];
    }

    private function buildCanonicalUrl(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        return (string)$uri->withQuery('')->withFragment('');
    }
}
