<?php

namespace App\Controllers\Admin;

use App\Models\Shop;
use App\Models\SeoMetadata;
use App\Services\LocalStorageService;
use Slim\Psr7\Response;
use Valitron\Validator;

class SetupController extends AppController
{
    private const HERO_TYPES = ['static', 'carousel', 'text', 'grid'];

    public function basic($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        return $this->render($response, 'setup/basic.twig', [
            'shop' => $shop,
            'errors' => $this->flashGet('errors', []),
            'data' => $this->flashGet('old', []),
        ]);
    }

    public function updateBasic($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $validator = new Validator($data);
        $validator->rule('required', 'shop_name')->message('Shop name is required.');
        $validator->rule('lengthMax', 'shop_name', 255)->message('Shop name is too long.');
        $validator->rule('lengthMax', 'shop_description', 500)->message('Description is too long.');

        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        $uploads = $request->getUploadedFiles();
        $logoFile = $uploads['logo'] ?? null;
        $faviconFile = $uploads['favicon'] ?? null;

        $maxSizeBytes = 3 * 1024 * 1024;
        $errors = array_merge($errors, $this->validateUpload($logoFile, 'logo', $maxSizeBytes));
        $errors = array_merge($errors, $this->validateUpload($faviconFile, 'favicon', $maxSizeBytes));

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/basic');
        }

        $storage = new LocalStorageService();
        $updates = [
            'shop_name' => (string)($data['shop_name'] ?? ''),
            'shop_description' => (string)($data['shop_description'] ?? ''),
        ];

        try {
            if ($logoFile && $logoFile->getError() === UPLOAD_ERR_OK) {
                $updates['logo_path'] = $storage->storeShopBranding($logoFile, $shop->id, 'logo', 512, 512);
            }

            if ($faviconFile && $faviconFile->getError() === UPLOAD_ERR_OK) {
                $updates['favicon_path'] = $storage->storeShopBranding($faviconFile, $shop->id, 'favicon', 64, 64);
            }

        } catch (\RuntimeException $exception) {
            $this->flashSet('errors', ['uploads' => $exception->getMessage()]);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/basic');
        }

        $shop->update($updates);
        $this->flashSet('success', 'Basic setup saved.');

        return $this->redirect($response, '/admin/setup/basic');
    }

    public function seo($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $seoMetadata = SeoMetadata::where('entity_type', 'shop')
            ->where('entity_id', $shop->id)
            ->first();

        return $this->render($response, 'setup/seo.twig', [
            'shop' => $shop,
            'seo_metadata' => $seoMetadata,
            'errors' => $this->flashGet('errors', []),
            'data' => $this->flashGet('old', []),
        ]);
    }

    public function updateSeo($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $seo = $data['seo'] ?? [];

        $validator = new Validator($data);
        $validator->rule('lengthMax', 'seo.seo_title', 255)->message('SEO title is too long.');
        $validator->rule('lengthMax', 'seo.seo_description', 500)->message('SEO description is too long.');
        $validator->rule('lengthMax', 'seo.seo_keywords', 255)->message('SEO keywords are too long.');
        $validator->rule('lengthMax', 'seo.canonical_url', 255)->message('Canonical URL is too long.');
        $validator->rule('lengthMax', 'seo.og_title', 255)->message('OG title is too long.');
        $validator->rule('lengthMax', 'seo.og_description', 500)->message('OG description is too long.');

        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());
        $uploads = $request->getUploadedFiles();
        $ogImageFile = $uploads['og_image'] ?? null;
        if ($ogImageFile) {
            $maxSizeBytes = 3 * 1024 * 1024;
            $errors = array_merge($errors, $this->validateUpload($ogImageFile, 'og_image', $maxSizeBytes));
        }
        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/seo');
        }

        $record = SeoMetadata::firstOrNew([
            'entity_type' => 'shop',
            'entity_id' => $shop->id,
        ]);

        $record->seo_title = $seo['seo_title'] ?? null;
        $record->seo_description = $seo['seo_description'] ?? null;
        $record->seo_keywords = $seo['seo_keywords'] ?? null;
        $record->canonical_url = $seo['canonical_url'] ?? null;
        $record->og_title = $seo['og_title'] ?? null;
        $record->og_description = $seo['og_description'] ?? null;
        if ($ogImageFile && $ogImageFile->getError() === UPLOAD_ERR_OK) {
            $storage = new LocalStorageService();
            try {
                $record->og_image = $storage->storeShopImageFit($ogImageFile, $shop->id, 'og', 1200, 630);
            } catch (\RuntimeException $exception) {
                $this->flashSet('errors', ['og_image' => $exception->getMessage()]);
                $this->flashSet('old', $data);
                return $this->redirect($response, '/admin/setup/seo');
            }
        }
        $record->save();

        $this->flashSet('success', 'SEO settings saved.');

        return $this->redirect($response, '/admin/setup/seo');
    }

    public function themes($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $heroSettings = json_decode((string)($shop->hero_settings ?? ''), true);
        if (!is_array($heroSettings)) {
            $heroSettings = [];
        }

        return $this->render($response, 'setup/themes.twig', [
            'shop' => $shop,
            'hero_settings' => $heroSettings,
            'themes' => $this->getAvailableThemes(),
            'errors' => $this->flashGet('errors', []),
            'data' => $this->flashGet('old', []),
        ]);
    }

    public function updateThemes($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $hero = $data['hero'] ?? [];
        $theme = (string)($data['theme'] ?? $shop->theme);

        $validator = new Validator($data);
        $validator->rule('in', 'hero.type', self::HERO_TYPES)->message('Select a valid hero type.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        if (!in_array($theme, $this->getAvailableThemes(), true)) {
            $errors['theme'] = 'Select a valid theme.';
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/themes');
        }

        $heroSettings = [
            'autoplay' => !empty($hero['autoplay']),
            'max_slides' => min(3, max(1, (int)($hero['slides'] ?? 2))),
        ];

        $shop->update([
            'theme' => $theme,
            'hero_type' => (string)($hero['type'] ?? 'carousel'),
            'hero_settings' => json_encode($heroSettings),
        ]);

        $this->flashSet('success', 'Theme settings saved.');

        return $this->redirect($response, '/admin/setup/themes');
    }

    public function payments($request, Response $response): Response
    {
        return $this->render($response, 'setup/payments.twig');
    }

    public function delivery($request, Response $response): Response
    {
        return $this->render($response, 'setup/delivery.twig');
    }

    public function discounts($request, Response $response): Response
    {
        return $this->render($response, 'setup/discounts.twig');
    }

    private function getShopOrRedirect(Response $response): Shop|Response
    {
        $this->startSession();
        $shopId = (int)($_SESSION['shop_id'] ?? 0);
        if ($shopId <= 0) {
            $this->flashSet('error', 'Shop settings are not available for this account.');
            return $this->redirect($response, '/admin/dashboard');
        }

        $shop = Shop::find($shopId);
        if (!$shop) {
            return $response->withStatus(404);
        }

        return $shop;
    }

    private function getAvailableThemes(): array
    {
        return $this->themeResolver()->getAvailableThemes();
    }

    private function validateUpload(?\Psr\Http\Message\UploadedFileInterface $file, string $field, int $maxSizeBytes): array
    {
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return [$field => 'Upload failed.'];
        }

        if ($file->getSize() > $maxSizeBytes) {
            return [$field => 'Image must be less than 3MB.'];
        }

        $mimeType = (string)$file->getClientMediaType();
        $allowed = ['image/png', 'image/jpeg', 'image/webp'];
        if (!in_array($mimeType, $allowed, true)) {
            return [$field => 'Only PNG, JPG, or WebP images are allowed.'];
        }

        return [];
    }
}
