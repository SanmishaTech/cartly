<?php

namespace App\Controllers\Admin;

use App\Config\FontConfig;
use App\Models\Shop;
use App\Models\SeoMetadata;
use App\Services\LocalStorageService;
use Slim\Psr7\Response;
use Valitron\Validator;

class SetupController extends AppController
{
    private const HERO_TYPES = ['banner', 'slider', 'headline'];

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
        $validator->rule('lengthMax', 'address_line1', 255)->message('Address line 1 is too long.');
        $validator->rule('lengthMax', 'address_line2', 255)->message('Address line 2 is too long.');
        $validator->rule('lengthMax', 'city', 100)->message('City is too long.');
        $validator->rule('lengthMax', 'state', 100)->message('State is too long.');
        $validator->rule('lengthMax', 'postal_code', 20)->message('Postal code is too long.');
        $validator->rule('lengthMax', 'country', 100)->message('Country is too long.');

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
        $addressLine1 = trim((string)($data['address_line1'] ?? ''));
        $addressLine2 = trim((string)($data['address_line2'] ?? ''));
        $city = trim((string)($data['city'] ?? ''));
        $state = trim((string)($data['state'] ?? ''));
        $postalCode = trim((string)($data['postal_code'] ?? ''));
        $country = trim((string)($data['country'] ?? ''));
        if ($country === '') {
            $country = 'India';
        }

        $updates = [
            'shop_name' => (string)($data['shop_name'] ?? ''),
            'shop_description' => (string)($data['shop_description'] ?? ''),
            'address_line1' => $addressLine1 !== '' ? $addressLine1 : null,
            'address_line2' => $addressLine2 !== '' ? $addressLine2 : null,
            'city' => $city !== '' ? $city : null,
            'state' => $state !== '' ? $state : null,
            'postal_code' => $postalCode !== '' ? $postalCode : null,
            'country' => $country,
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
        $shop->refresh();

        $seoRecord = SeoMetadata::firstOrNew([
            'entity_type' => 'shop',
            'entity_id' => $shop->id,
        ]);
        $seoRecord->schema_json = $this->buildShopSchema($shop, $request, [
            'facebook' => $seoRecord->facebook ?? null,
            'instagram' => $seoRecord->instagram ?? null,
        ]);
        $seoRecord->save();
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
        $validator->rule('lengthMax', 'seo.facebook', 255)->message('Facebook link is too long.');
        $validator->rule('lengthMax', 'seo.instagram', 255)->message('Instagram link is too long.');
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
        $record->facebook = $seo['facebook'] ?? null;
        $record->instagram = $seo['instagram'] ?? null;
        $record->schema_json = $this->buildShopSchema($shop, $request, $seo);
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

    public function hero($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $heroSettings = $shop->hero_settings ?? [];
        if (!is_array($heroSettings)) {
            $heroSettings = json_decode((string)$heroSettings, true);
        }
        if (!is_array($heroSettings)) {
            $heroSettings = [];
        }

        return $this->render($response, 'setup/hero.twig', [
            'shop' => $shop,
            'hero_type' => $shop->hero_type ?? 'banner',
            'hero_settings' => $heroSettings,
            'errors' => $this->flashGet('errors', []),
            'data' => $this->flashGet('old', []),
        ]);
    }

    public function updateHero($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $hero = (array)($data['hero'] ?? []);
        $heroType = trim((string)($hero['type'] ?? ''));
        $settingsInput = (array)($hero['settings'] ?? []);
        $errors = [];

        if (!in_array($heroType, self::HERO_TYPES, true)) {
            $errors['hero.type'] = 'Select a valid hero type.';
        }

        $uploads = $request->getUploadedFiles();
        $storage = new LocalStorageService();
        $maxSizeBytes = 3 * 1024 * 1024;
        $existingSettings = $shop->hero_settings ?? [];
        if (!is_array($existingSettings)) {
            $existingSettings = json_decode((string)$existingSettings, true);
        }
        if (!is_array($existingSettings)) {
            $existingSettings = [];
        }
        $heroSettings = $existingSettings;

        if ($heroType === 'banner') {
            $bannerInput = (array)($settingsInput['banner'] ?? []);
            $content = (array)($bannerInput['content'] ?? []);
            $title = trim((string)($content['title'] ?? ''));
            $subtitle = trim((string)($content['subtitle'] ?? ''));
            $align = trim((string)($content['align'] ?? 'left'));
            if ($align === '') {
                $align = 'left';
            }
            $overlay = $content['overlay'] ?? 0.4;
            if ($overlay === '' || $overlay === null) {
                $overlay = 0.4;
            }
            $imagePath = trim((string)($content['image'] ?? ''));

            if ($title === '') {
                $errors['hero.content.title'] = 'Banner title is required.';
            }
            if (!in_array($align, ['left', 'center'], true)) {
                $errors['hero.content.align'] = 'Choose a valid alignment.';
            }
            if (!is_numeric($overlay) || (float)$overlay < 0 || (float)$overlay > 0.8) {
                $errors['hero.content.overlay'] = 'Overlay must be between 0.0 and 0.8.';
            }

            $cta = (array)($content['cta'] ?? []);
            $ctaText = trim((string)($cta['text'] ?? ''));
            $ctaLink = trim((string)($cta['link'] ?? ''));
            if (($ctaText !== '' && $ctaLink === '') || ($ctaLink !== '' && $ctaText === '')) {
                $errors['hero.content.cta'] = 'CTA text and link are required together.';
            }

            $bannerUpload = $uploads['hero_banner_image'] ?? null;
            $errors = array_merge($errors, $this->validateUpload($bannerUpload, 'hero_banner_image', $maxSizeBytes));
            if (empty($errors['hero_banner_image']) && $bannerUpload && $bannerUpload->getError() === UPLOAD_ERR_OK) {
                try {
                    $imagePath = $storage->storeShopImageFit($bannerUpload, $shop->id, 'hero_banner', 1600, 900);
                } catch (\RuntimeException $exception) {
                    $errors['hero_banner_image'] = $exception->getMessage();
                }
            }

            if ($imagePath === '') {
                $errors['hero.content.image'] = 'Banner image is required.';
            }

            $heroSettings['banner'] = [
                'content' => [
                    'title' => $title,
                    'subtitle' => $subtitle !== '' ? $subtitle : null,
                    'image' => $imagePath,
                    'cta' => $ctaText !== '' && $ctaLink !== '' ? [
                        'text' => $ctaText,
                        'link' => $ctaLink,
                    ] : null,
                    'align' => $align,
                    'overlay' => (float)$overlay,
                ],
            ];
        }

        if ($heroType === 'slider') {
            $sliderInput = (array)($settingsInput['slider'] ?? []);
            $config = (array)($sliderInput['config'] ?? []);
            $autoplay = !empty($config['autoplay']);
            $interval = (int)($config['interval'] ?? 4000);
            if ($interval <= 0) {
                $interval = 4000;
            }

            $slidesInput = $sliderInput['slides'] ?? [];
            if (!is_array($slidesInput)) {
                $slidesInput = [];
            }
            $slideUploads = $uploads['hero_slide_images'] ?? [];
            $slides = [];

            foreach ($slidesInput as $index => $slideInput) {
                if (!is_array($slideInput)) {
                    continue;
                }
                $title = trim((string)($slideInput['title'] ?? ''));
                $subtitle = trim((string)($slideInput['subtitle'] ?? ''));
                $imagePath = trim((string)($slideInput['image'] ?? ''));
                $cta = (array)($slideInput['cta'] ?? []);
                $ctaText = trim((string)($cta['text'] ?? ''));
                $ctaLink = trim((string)($cta['link'] ?? ''));
                if (($ctaText !== '' && $ctaLink === '') || ($ctaLink !== '' && $ctaText === '')) {
                    $errors["hero.slides.{$index}.cta"] = 'CTA text and link are required together.';
                }

                $slideUpload = is_array($slideUploads) ? ($slideUploads[$index] ?? null) : null;
                $errors = array_merge($errors, $this->validateUpload($slideUpload, "hero_slide_images.{$index}", $maxSizeBytes));
                if (empty($errors["hero_slide_images.{$index}"]) && $slideUpload && $slideUpload->getError() === UPLOAD_ERR_OK) {
                    try {
                        $imagePath = $storage->storeShopImageFit($slideUpload, $shop->id, "hero_slide_{$index}", 1600, 900);
                    } catch (\RuntimeException $exception) {
                        $errors["hero_slide_images.{$index}"] = $exception->getMessage();
                    }
                }

                if ($imagePath === '') {
                    $errors["hero.slides.{$index}.image"] = 'Slide image is required.';
                }

                $slides[] = [
                    'title' => $title !== '' ? $title : null,
                    'subtitle' => $subtitle !== '' ? $subtitle : null,
                    'image' => $imagePath,
                    'cta' => $ctaText !== '' && $ctaLink !== '' ? [
                        'text' => $ctaText,
                        'link' => $ctaLink,
                    ] : null,
                ];
            }

            if (count($slides) < 1) {
                $errors['hero.slides'] = 'At least one slide is required.';
            }

            $heroSettings['slider'] = [
                'config' => [
                    'autoplay' => $autoplay,
                    'interval' => $interval,
                ],
                'slides' => $slides,
            ];
        }

        if ($heroType === 'headline') {
            $headlineInput = (array)($settingsInput['headline'] ?? []);
            $content = (array)($headlineInput['content'] ?? []);
            $title = trim((string)($content['title'] ?? ''));
            $description = trim((string)($content['description'] ?? ''));
            $imagePath = trim((string)($content['image'] ?? ''));

            if ($title === '') {
                $errors['hero.content.title'] = 'Headline title is required.';
            }
            if ($description === '') {
                $errors['hero.content.description'] = 'Headline description is required.';
            }
            if ($imagePath !== '') {
                $errors['hero.content.image'] = 'Headline hero does not allow images.';
            }

            $cta = (array)($content['cta'] ?? []);
            $ctaText = trim((string)($cta['text'] ?? ''));
            $ctaLink = trim((string)($cta['link'] ?? ''));
            if (($ctaText !== '' && $ctaLink === '') || ($ctaLink !== '' && $ctaText === '')) {
                $errors['hero.content.cta'] = 'CTA text and link are required together.';
            }

            $heroSettings['headline'] = [
                'content' => [
                    'title' => $title,
                    'description' => $description,
                    'cta' => $ctaText !== '' && $ctaLink !== '' ? [
                        'text' => $ctaText,
                        'link' => $ctaLink,
                    ] : null,
                ],
            ];
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/hero');
        }

        $shop->update([
            'hero_type' => $heroType,
            'hero_settings' => $heroSettings,
        ]);

        $this->flashSet('success', 'Hero settings saved.');

        return $this->redirect($response, '/admin/setup/hero');
    }

    public function themes($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $themeDefaults = $this->getThemeDefaults((string)($shop->theme ?? 'default'));
        $themeOverrides = $this->decodeThemeConfig($shop->theme_config ?? null);
        $themeConfig = $this->mergeThemeConfig($themeDefaults, $themeOverrides);
        $themes = $this->getAvailableThemes();

        return $this->render($response, 'setup/themes.twig', [
            'shop' => $shop,
            'themes' => $themes,
            'theme_defaults' => $themeDefaults,
            'theme_config' => $themeConfig,
            'font_profiles' => FontConfig::profiles(),
            'theme_previews' => $this->buildThemePreviews($themes),
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
        $theme = (string)($data['theme'] ?? $shop->theme);
        $themeConfigInput = $data['theme_config'] ?? [];
        $resetTheme = !empty($data['reset_theme']);

        $validator = new Validator($data);
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        if (!in_array($theme, $this->getAvailableThemes(), true)) {
            $errors['theme'] = 'Select a valid theme.';
        }

        $themeConfig = $resetTheme ? [] : $this->sanitizeThemeConfig($themeConfigInput, $errors);

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/themes');
        }

        $shop->update([
            'theme' => $theme,
            'theme_config' => empty($themeConfig) ? null : json_encode($themeConfig),
        ]);

        $this->flashSet('success', $resetTheme ? 'Theme settings reset.' : 'Theme settings saved.');

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

    private function getThemeDefaults(string $themeName): array
    {
        $baseDefaults = [
            'colors' => [
                'primary' => '#2563eb',
                'primary_content' => '#ffffff',
                'secondary' => '#64748b',
                'secondary_content' => '#ffffff',
                'accent' => '#f59e0b',
                'accent_content' => '#ffffff',
                'background' => '#ffffff',
                'surface' => '#ffffff',
                'surface_alt' => '#f1f5f9',
                'text' => '#0f172a',
                'text_muted' => '#64748b',
                'border' => '#e2e8f0',
                'light' => '#ffffff',
                'dark' => '#0f172a',
                'base_200' => '#f1f5f9',
                'base_300' => '#e2e8f0',
            ],
            'radii' => [
                'sm' => '4px',
                'md' => '8px',
                'lg' => '12px',
            ],
            'text_sizes' => [
                'xs' => '12px',
                'sm' => '14px',
                'base' => '16px',
                'lg' => '18px',
                'xl' => '22px',
            ],
            'font_profile' => 'system',
            'font_profile_base' => 'system',
            'font_profile_heading' => 'system',
        ];

        $themeMetadata = $this->themeResolver()->getThemeMetadata($themeName) ?? [];

        return $this->mergeThemeConfig($baseDefaults, $themeMetadata);
    }

    private function decodeThemeConfig(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function mergeThemeConfig(array $defaults, array $overrides): array
    {
        return array_replace_recursive($defaults, $overrides);
    }

    private function sanitizeThemeConfig(array $input, array &$errors): array
    {
        $colors = (array)($input['colors'] ?? []);
        $radii = (array)($input['radii'] ?? []);
        $textSizes = (array)($input['text_sizes'] ?? []);
        $fontProfile = $input['font_profile'] ?? null;
        $fontProfileBase = $input['font_profile_base'] ?? null;
        $fontProfileHeading = $input['font_profile_heading'] ?? null;

        $clean = [
            'colors' => [],
            'radii' => [],
            'text_sizes' => [],
        ];

        $colorFields = [
            'primary',
            'primary_content',
            'secondary',
            'secondary_content',
            'accent',
            'accent_content',
            'background',
            'surface',
            'surface_alt',
            'text',
            'text_muted',
            'border',
            'light',
            'dark',
            'base_200',
            'base_300',
        ];

        foreach ($colorFields as $field) {
            $value = $this->sanitizeColor($colors[$field] ?? null, "theme_config.colors.{$field}", $errors);
            if ($value !== null) {
                $clean['colors'][$field] = $value;
            }
        }

        $radiusFields = ['sm', 'md', 'lg'];
        foreach ($radiusFields as $field) {
            $value = $this->sanitizeSize($radii[$field] ?? null, "theme_config.radii.{$field}", $errors);
            if ($value !== null) {
                $clean['radii'][$field] = $value;
            }
        }

        $textSizeFields = ['xs', 'sm', 'base', 'lg', 'xl'];
        foreach ($textSizeFields as $field) {
            $value = $this->sanitizeSize($textSizes[$field] ?? null, "theme_config.text_sizes.{$field}", $errors);
            if ($value !== null) {
                $clean['text_sizes'][$field] = $value;
            }
        }

        $profileValue = $this->sanitizeFontProfile($fontProfile, $errors);
        if ($profileValue !== null) {
            $clean['font_profile'] = $profileValue;
        }

        $baseValue = $this->sanitizeFontProfile($fontProfileBase, $errors, 'theme_config.font_profile_base');
        if ($baseValue !== null) {
            $clean['font_profile_base'] = $baseValue;
        }

        $headingValue = $this->sanitizeFontProfile($fontProfileHeading, $errors, 'theme_config.font_profile_heading');
        if ($headingValue !== null) {
            $clean['font_profile_heading'] = $headingValue;
        }

        $clean = array_filter($clean, fn($values) => !empty($values));

        return $clean;
    }

    private function sanitizeColor(mixed $value, string $field, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim((string)$value));
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/', $value)) {
            $errors[$field] = 'Enter a valid hex color (e.g., #2563EB).';
            return null;
        }

        return $value;
    }

    private function sanitizeFontProfile(mixed $value, array &$errors, string $fieldKey = 'theme_config.font_profile'): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (!in_array($value, FontConfig::keys(), true)) {
            $errors[$fieldKey] = 'Select a valid font profile.';
            return null;
        }

        return $value;
    }

    private function sanitizeSize(mixed $value, string $field, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d+(\.\d+)?(px|rem|em|%)$/', $value)) {
            $errors[$field] = 'Use a valid size with unit (e.g., 14px).';
            return null;
        }

        return $value;
    }

    private function buildThemePreviews(array $themes): array
    {
        $previews = [];
        foreach ($themes as $themeName) {
            $metadata = $this->themeResolver()->getThemeMetadata($themeName) ?? [];
            $previewImage = $metadata['preview_image'] ?? null;
            $previewUrl = null;
            if ($previewImage) {
                $previewUrl = '/assets/themes/' . $themeName . '/' . ltrim((string)$previewImage, '/');
            }
            $previews[$themeName] = [
                'label' => $metadata['name'] ?? ucfirst($themeName),
                'description' => $metadata['description'] ?? '',
                'preview_url' => $previewUrl,
            ];
        }

        return $previews;
    }

    private function buildShopSchema(Shop $shop, $request, array $seo): array
    {
        $baseUrl = $this->resolveShopUrl($shop, $request);
        $logoUrl = $this->resolveShopLogoUrl($shop, $baseUrl);
        $streetAddress = trim(implode(', ', array_filter([
            trim((string)($shop->address_line1 ?? '')),
            trim((string)($shop->address_line2 ?? '')),
        ])));
        $country = trim((string)($shop->country ?? ''));
        $countryCode = $this->resolveCountryCode($country);
        $areaServed = $countryCode !== '' ? $countryCode : $country;
        $supportEmail = (string)($_SESSION['user_email'] ?? '');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'OnlineStore',
            'name' => (string)($shop->shop_name ?? ''),
            'url' => $baseUrl,
            'logo' => $logoUrl,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $streetAddress,
                'addressLocality' => (string)($shop->city ?? ''),
                'addressRegion' => (string)($shop->state ?? ''),
                'postalCode' => (string)($shop->postal_code ?? ''),
                'addressCountry' => $countryCode !== '' ? $countryCode : $country,
            ],
            'areaServed' => $areaServed,
            'paymentAccepted' => ['UPI', 'Credit Card', 'Cash on Delivery'],
            'currenciesAccepted' => 'INR',
            'sameAs' => $this->buildSameAsLinks($seo),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'Customer Support',
                'email' => $supportEmail,
            ],
        ];
    }

    private function resolveShopUrl(Shop $shop, $request): string
    {
        $domain = $this->resolveShopDomain($shop);
        if ($domain === '') {
            $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?? '';
            return rtrim((string)$appUrl, '/');
        }

        $scheme = $request->getUri()->getScheme() ?: 'https';
        return $scheme . '://' . $domain;
    }

    private function resolveShopDomain(Shop $shop): string
    {
        $primaryDomain = '';
        if ($shop->relationLoaded('domains')) {
            foreach ($shop->domains as $domain) {
                if ($domain->is_primary) {
                    $primaryDomain = $domain->domain;
                    break;
                }
            }
            if ($primaryDomain === '' && $shop->domains->count() > 0) {
                $primaryDomain = (string)$shop->domains->first()->domain;
            }
        } else {
            $primaryDomain = (string)$shop->domains()
                ->where('is_primary', true)
                ->value('domain');
            if ($primaryDomain === '') {
                $primaryDomain = (string)$shop->domains()->value('domain');
            }
        }

        return $primaryDomain;
    }

    private function resolveShopLogoUrl(Shop $shop, string $baseUrl): string
    {
        if (!$shop->logo_path) {
            return '';
        }
        if ($baseUrl === '') {
            return '';
        }
        return rtrim($baseUrl, '/') . '/media/' . ltrim((string)$shop->logo_path, '/');
    }

    private function resolveCountryCode(string $country): string
    {
        if ($country === '') {
            return '';
        }
        if (strcasecmp($country, 'India') === 0) {
            return 'IN';
        }
        return $country;
    }

    private function buildSameAsLinks(array $seo): array
    {
        $links = [];
        $instagram = trim((string)($seo['instagram'] ?? ''));
        $facebook = trim((string)($seo['facebook'] ?? ''));
        if ($instagram !== '') {
            $links[] = $instagram;
        }
        if ($facebook !== '') {
            $links[] = $facebook;
        }
        return $links;
    }
}
