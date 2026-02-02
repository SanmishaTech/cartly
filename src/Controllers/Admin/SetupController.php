<?php

namespace App\Controllers\Admin;

use App\Config\FontConfig;
use App\Helpers\HomeSectionConfig;
use App\Models\Shop;
use App\Models\SeoMetadata;
use App\Models\ShopEmailSettings;
use App\Models\ShopMetadata;
use App\Services\LocalStorageService;
use App\Services\TransactionalMailService;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use App\Services\ThemeResolver;
use Valitron\Validator;

class SetupController extends AppController
{
    private const HERO_TYPES = ['banner', 'slider', 'headline'];

    public function __construct(
        Twig $view,
        ThemeResolver $themeResolver,
        private TransactionalMailService $transactionalMailService
    ) {
        parent::__construct($view, $themeResolver);
    }

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

        $shop->update(['shop_name' => (string)($data['shop_name'] ?? '')]);

        $metadata = ShopMetadata::firstOrNew(['shop_id' => $shop->id]);
        $metadata->shop_description = (string)($data['shop_description'] ?? '');
        $metadata->address_line1 = $addressLine1 !== '' ? $addressLine1 : null;
        $metadata->address_line2 = $addressLine2 !== '' ? $addressLine2 : null;
        $metadata->city = $city !== '' ? $city : null;
        $metadata->state = $state !== '' ? $state : null;
        $metadata->postal_code = $postalCode !== '' ? $postalCode : null;
        $metadata->country = $country;

        try {
            if ($logoFile && $logoFile->getError() === UPLOAD_ERR_OK) {
                $metadata->logo_path = $storage->storeShopBranding($logoFile, $shop->id, 'logo', 512, 512);
            }

            if ($faviconFile && $faviconFile->getError() === UPLOAD_ERR_OK) {
                $metadata->favicon_path = $storage->storeShopBranding($faviconFile, $shop->id, 'favicon', 64, 64);
            }
        } catch (\RuntimeException $exception) {
            $this->flashSet('errors', ['uploads' => $exception->getMessage()]);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/basic');
        }

        $metadata->save();
        $shop->refresh();
        $shop->setRelation('metadata', $metadata);

        $seoRecord = SeoMetadata::firstOrNew([
            'entity_type' => 'shop',
            'entity_id' => $shop->id,
        ]);
        $shopMetadata = ShopMetadata::where('shop_id', $shop->id)->first();
        $socialLinks = $shopMetadata?->social_media_links ?? [];
        if (!is_array($socialLinks)) {
            $socialLinks = [];
        }
        $seoRecord->schema_json = $this->buildShopSchema($shop, $request, $socialLinks);
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
        $shopMetadata = ShopMetadata::where('shop_id', $shop->id)->first();

        return $this->render($response, 'setup/seo.twig', [
            'shop' => $shop,
            'seo_metadata' => $seoMetadata,
            'shop_metadata' => $shopMetadata,
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
        $thirdPartyInput = $data['third_party'] ?? [];

        $validator = new Validator($data);
        $validator->rule('lengthMax', 'seo.seo_title', 255)->message('SEO title is too long.');
        $validator->rule('lengthMax', 'seo.seo_description', 500)->message('SEO description is too long.');
        $validator->rule('lengthMax', 'seo.seo_keywords', 255)->message('SEO keywords are too long.');
        $validator->rule('lengthMax', 'seo.canonical_url', 255)->message('Canonical URL is too long.');
        $validator->rule('lengthMax', 'seo.facebook', 255)->message('Facebook link is too long.');
        $validator->rule('lengthMax', 'seo.instagram', 255)->message('Instagram link is too long.');
        $validator->rule('lengthMax', 'seo.x', 255)->message('X link is too long.');
        $validator->rule('lengthMax', 'seo.linkedin', 255)->message('LinkedIn link is too long.');
        $validator->rule('lengthMax', 'seo.youtube', 255)->message('YouTube link is too long.');
        $validator->rule('lengthMax', 'seo.pinterest', 255)->message('Pinterest link is too long.');
        $validator->rule('lengthMax', 'seo.whatsapp', 255)->message('WhatsApp link is too long.');
        $validator->rule('url', 'seo.facebook')->message('Enter a valid Facebook URL.');
        $validator->rule('url', 'seo.instagram')->message('Enter a valid Instagram URL.');
        $validator->rule('url', 'seo.x')->message('Enter a valid X URL.');
        $validator->rule('url', 'seo.linkedin')->message('Enter a valid LinkedIn URL.');
        $validator->rule('url', 'seo.youtube')->message('Enter a valid YouTube URL.');
        $validator->rule('url', 'seo.pinterest')->message('Enter a valid Pinterest URL.');
        $validator->rule('url', 'seo.whatsapp')->message('Enter a valid WhatsApp URL.');
        $validator->rule('lengthMax', 'seo.og_title', 255)->message('OG title is too long.');
        $validator->rule('lengthMax', 'seo.og_description', 500)->message('OG description is too long.');

        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());
        $thirdParty = $this->sanitizeThirdParty($thirdPartyInput, $errors);
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
        $socialLinks = $this->sanitizeSocialLinks($seo);
        $record->schema_json = $this->buildShopSchema($shop, $request, $socialLinks);
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

        $shopMetadata = ShopMetadata::firstOrNew([
            'shop_id' => $shop->id,
        ]);
        $shopMetadata->social_media_links = $socialLinks;
        $shopMetadata->third_party = $thirdParty;
        $shopMetadata->save();

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

        $metadata = ShopMetadata::firstOrNew(['shop_id' => $shop->id]);
        $metadata->hero_type = $heroType;
        $metadata->hero_settings = $heroSettings;
        $metadata->save();

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

        $metadata = ShopMetadata::firstOrNew(['shop_id' => $shop->id]);
        $metadata->theme = $theme;
        $metadata->theme_config = empty($themeConfig) ? null : $themeConfig;
        $metadata->save();

        $this->flashSet('success', $resetTheme ? 'Theme settings reset.' : 'Theme settings saved.');

        return $this->redirect($response, '/admin/setup/themes');
    }

    public function home($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $shopMetadata = ShopMetadata::where('shop_id', $shop->id)->first();
        $data = $this->flashGet('old', []);
        $sectionsInput = $data['home_sections'] ?? ($shopMetadata?->home_sections ?? []);
        $homeSections = HomeSectionConfig::normalizeSections(is_array($sectionsInput) ? $sectionsInput : []);
        $homeContent = $data['home_content'] ?? ($shopMetadata?->home_content ?? []);
        if (!is_array($homeContent)) {
            $homeContent = [];
        }

        return $this->render($response, 'setup/home.twig', [
            'shop' => $shop,
            'home_sections' => $homeSections,
            'home_content' => $homeContent,
            'content_defaults' => HomeSectionConfig::defaultContent(),
            'section_labels' => HomeSectionConfig::sectionLabels(),
            'errors' => $this->flashGet('errors', []),
            'data' => $data,
        ]);
    }

    public function updateHome($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $resetHome = !empty($data['reset_home']);
        $sectionsInput = $data['home_sections'] ?? [];
        $contentInput = $data['home_content'] ?? [];

        $validator = new Validator($data);
        $validator->rule('lengthMax', 'home_content.about.title', 255)->message('About heading is too long.');
        $validator->rule('lengthMax', 'home_content.about.description', 500)->message('About description is too long.');
        $validator->rule('lengthMax', 'home_content.featured_products.title', 255)->message('Featured heading is too long.');
        $validator->rule('lengthMax', 'home_content.featured_products.subtitle', 255)->message('Featured subheading is too long.');
        $validator->rule('lengthMax', 'home_content.categories.title', 255)->message('Categories heading is too long.');
        $validator->rule('lengthMax', 'home_content.categories.subtitle', 255)->message('Categories subheading is too long.');
        $validator->rule('lengthMax', 'home_content.popular_new.title', 255)->message('Popular & New heading is too long.');
        $validator->rule('lengthMax', 'home_content.popular_new.subtitle', 255)->message('Popular & New subheading is too long.');
        $validator->rule('lengthMax', 'home_content.promo.badge', 120)->message('Promo badge is too long.');
        $validator->rule('lengthMax', 'home_content.promo.title', 255)->message('Promo heading is too long.');
        $validator->rule('lengthMax', 'home_content.promo.body', 500)->message('Promo description is too long.');
        $validator->rule('lengthMax', 'home_content.promo.cta_text', 120)->message('Promo button text is too long.');
        $validator->rule('lengthMax', 'home_content.promo.cta_link', 255)->message('Promo link is too long.');
        $validator->rule('lengthMax', 'home_content.testimonials.title', 255)->message('Testimonials heading is too long.');
        $validator->rule('lengthMax', 'home_content.testimonials.subtitle', 255)->message('Testimonials subheading is too long.');
        $validator->rule('lengthMax', 'home_content.newsletter.title', 255)->message('Newsletter heading is too long.');
        $validator->rule('lengthMax', 'home_content.newsletter.subtitle', 255)->message('Newsletter subheading is too long.');
        $validator->rule('lengthMax', 'home_content.newsletter.cta_text', 120)->message('Newsletter button text is too long.');

        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        $homeSections = $resetHome ? HomeSectionConfig::defaultSections() : HomeSectionConfig::normalizeSections(is_array($sectionsInput) ? $sectionsInput : []);
        $homeContent = $resetHome ? HomeSectionConfig::defaultContent() : $this->sanitizeHomeContent(is_array($contentInput) ? $contentInput : [], $errors);

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/home');
        }

        $shopMetadata = ShopMetadata::firstOrNew([
            'shop_id' => $shop->id,
        ]);
        $shopMetadata->home_sections = $homeSections;
        $shopMetadata->home_content = empty($homeContent) ? null : $homeContent;
        $shopMetadata->save();

        $this->flashSet('success', $resetHome ? 'Home settings reset.' : 'Home settings saved.');

        return $this->redirect($response, '/admin/setup/home');
    }

    public function footer($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $shopMetadata = ShopMetadata::where('shop_id', $shop->id)->first();
        $data = $this->flashGet('old', []);
        $footerContent = $data['footer'] ?? ($shopMetadata?->footer_content ?? []);
        if (!is_array($footerContent)) {
            $footerContent = [];
        }

        return $this->render($response, 'setup/footer.twig', [
            'shop' => $shop,
            'footer_content' => $footerContent,
            'errors' => $this->flashGet('errors', []),
            'data' => $data,
        ]);
    }

    public function updateFooter($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $footerInput = $data['footer'] ?? [];
        $errors = [];

        $footerContent = $this->sanitizeFooterContent(is_array($footerInput) ? $footerInput : [], $errors);

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/footer');
        }

        $shopMetadata = ShopMetadata::firstOrNew([
            'shop_id' => $shop->id,
        ]);
        $shopMetadata->footer_content = empty($footerContent) ? null : $footerContent;
        $shopMetadata->save();

        $this->flashSet('success', 'Footer settings saved.');

        return $this->redirect($response, '/admin/setup/footer');
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

    public function customerAuth($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $shopMetadata = ShopMetadata::where('shop_id', $shop->id)->first();
        $raw = $shopMetadata?->oauth_config ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        $oauthConfig = [
            'google' => array_merge(['enabled' => false, 'client_id' => '', 'client_secret' => ''], $raw['google'] ?? []),
            'facebook' => array_merge(['enabled' => false, 'app_id' => '', 'app_secret' => ''], $raw['facebook'] ?? []),
        ];

        $scheme = $request->getUri()->getScheme() ?: 'https';
        $redirectUris = $this->buildOAuthRedirectUris($shop, $scheme);

        return $this->render($response, 'setup/customer_auth.twig', [
            'shop' => $shop,
            'oauth_config' => $oauthConfig,
            'oauth_redirect_uris' => $redirectUris,
            'errors' => $this->flashGet('errors', []),
            'data' => $this->flashGet('old', []),
        ]);
    }

    public function updateCustomerAuth($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $oauth = (array)($data['oauth'] ?? []);

        $shopMetadata = ShopMetadata::firstOrNew(['shop_id' => $shop->id]);
        $existing = $shopMetadata->oauth_config ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }

        $googleEnabled = !empty($oauth['google']['enabled']);
        $facebookEnabled = !empty($oauth['facebook']['enabled']);

        $clientId = trim((string)($oauth['google']['client_id'] ?? ''));
        $clientSecret = trim((string)($oauth['google']['client_secret'] ?? ''));
        $appId = trim((string)($oauth['facebook']['app_id'] ?? ''));
        $appSecret = trim((string)($oauth['facebook']['app_secret'] ?? ''));

        $errors = [];
        if ($googleEnabled) {
            if ($clientId === '') {
                $errors['oauth.google.client_id'] = 'Client ID is required when Google Login is enabled.';
            } elseif (str_contains($clientId, '@')) {
                $errors['oauth.google.client_id'] = 'Client ID must be from Google Cloud Console (e.g. xxx.apps.googleusercontent.com), not an email.';
            } elseif ($clientId === 'root@demo.com') {
                $errors['oauth.google.client_id'] = 'Do not use demo login credentials. Use your Google OAuth Client ID from Google Cloud Console.';
            }
            $existingSecret = $existing['google']['client_secret'] ?? '';
            if ($clientSecret === '' && $existingSecret === '') {
                $errors['oauth.google.client_secret'] = 'Client Secret is required when Google Login is enabled.';
            } elseif ($clientSecret === 'abcd123@') {
                $errors['oauth.google.client_secret'] = 'Do not use demo password. Use your Google OAuth Client Secret from Google Cloud Console.';
            }
        } elseif ($clientId !== '' && (str_contains($clientId, '@') || $clientId === 'root@demo.com' || $clientSecret === 'abcd123@')) {
            $errors['oauth.google.client_id'] = 'Invalid credentials. Use real OAuth Client ID from Google Cloud Console or leave empty.';
        }
        if ($facebookEnabled) {
            if ($appId === '') {
                $errors['oauth.facebook.app_id'] = 'App ID is required when Facebook Login is enabled.';
            } elseif (str_contains($appId, '@') || $appId === 'root@demo.com') {
                $errors['oauth.facebook.app_id'] = 'App ID must be from Meta for Developers, not an email or demo credentials.';
            }
            $existingFbSecret = $existing['facebook']['app_secret'] ?? '';
            if ($appSecret === '' && $existingFbSecret === '') {
                $errors['oauth.facebook.app_secret'] = 'App Secret is required when Facebook Login is enabled.';
            } elseif ($appSecret === 'abcd123@') {
                $errors['oauth.facebook.app_secret'] = 'Do not use demo password. Use your Facebook App Secret from Meta for Developers.';
            }
        } elseif ($appId !== '' && (str_contains($appId, '@') || $appId === 'root@demo.com' || $appSecret === 'abcd123@')) {
            $errors['oauth.facebook.app_id'] = 'Invalid credentials. Use real App ID from Meta for Developers or leave empty.';
        }
        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/customer-auth');
        }

        $googleSecret = trim((string)($oauth['google']['client_secret'] ?? ''));
        $fbSecret = trim((string)($oauth['facebook']['app_secret'] ?? ''));

        $sanitizeGoogle = static function (string $v): string {
            if ($v === '' || str_contains($v, '@') || $v === 'root@demo.com') {
                return '';
            }
            return $v;
        };
        $sanitizeSecret = static function (string $v): string {
            return $v === 'abcd123@' ? '' : $v;
        };
        $safeClientId = $googleEnabled ? $sanitizeGoogle($clientId) : $sanitizeGoogle($existing['google']['client_id'] ?? '');
        $safeClientSecret = $googleEnabled ? $sanitizeSecret($clientSecret !== '' ? $clientSecret : ($existing['google']['client_secret'] ?? '')) : $sanitizeSecret($existing['google']['client_secret'] ?? '');
        $safeAppId = $facebookEnabled ? $sanitizeGoogle($appId) : $sanitizeGoogle($existing['facebook']['app_id'] ?? '');
        $safeAppSecret = $facebookEnabled ? $sanitizeSecret($fbSecret !== '' ? $fbSecret : ($existing['facebook']['app_secret'] ?? '')) : $sanitizeSecret($existing['facebook']['app_secret'] ?? '');

        $oauthConfig = [
            'google' => [
                'enabled' => $googleEnabled,
                'client_id' => $safeClientId,
                'client_secret' => $safeClientSecret,
            ],
            'facebook' => [
                'enabled' => $facebookEnabled,
                'app_id' => $safeAppId,
                'app_secret' => $safeAppSecret,
            ],
        ];
        $shopMetadata->oauth_config = $oauthConfig;
        $shopMetadata->save();

        $this->flashSet('success', 'Customer auth settings saved.');

        return $this->redirect($response, '/admin/setup/customer-auth');
    }

    public function email($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $settings = ShopEmailSettings::where('shop_id', $shop->id)->first();

        return $this->render($response, 'setup/email.twig', [
            'shop' => $shop,
            'email_settings' => $settings,
            'errors' => $this->flashGet('errors', []),
            'data' => $this->flashGet('old', []),
        ]);
    }

    public function updateEmail($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $email = (array)($data['email'] ?? []);

        $validator = new Validator($data);
        $validator->rule('lengthMax', 'email.from_email', 191)->message('From email is too long.');
        $validator->rule('lengthMax', 'email.from_name', 191)->message('From name is too long.');
        $validator->rule('lengthMax', 'email.reply_to_email', 191)->message('Reply-To email is too long.');
        $validator->rule('lengthMax', 'email.reply_to_name', 191)->message('Reply-To name is too long.');
        $validator->rule('lengthMax', 'email.domain', 191)->message('Domain is too long.');
        $validator->rule('optional', 'email.from_email');
        $validator->rule('email', 'email.from_email')->message('Enter a valid from email.');
        $validator->rule('optional', 'email.reply_to_email');
        $validator->rule('email', 'email.reply_to_email')->message('Enter a valid reply-to email.');

        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        $mode = trim((string)($email['mode'] ?? 'global'));
        if (!in_array($mode, [ShopEmailSettings::EMAIL_MODE_GLOBAL, ShopEmailSettings::EMAIL_MODE_CUSTOM_DOMAIN], true)) {
            $mode = ShopEmailSettings::EMAIL_MODE_GLOBAL;
        }

        $fromEmail = trim((string)($email['from_email'] ?? ''));
        $fromName = trim((string)($email['from_name'] ?? ''));
        $replyToEmail = trim((string)($email['reply_to_email'] ?? ''));
        $replyToName = trim((string)($email['reply_to_name'] ?? ''));
        $domain = trim((string)($email['domain'] ?? ''));

        if ($mode === ShopEmailSettings::EMAIL_MODE_CUSTOM_DOMAIN) {
            if ($fromEmail === '') {
                $errors['email.from_email'] = $errors['email.from_email'] ?? 'From email is required when sending from your domain.';
            }
            if ($domain === '') {
                $errors['email.domain'] = $errors['email.domain'] ?? 'Domain is required when sending from your domain.';
            }
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/setup/email');
        }

        $settings = ShopEmailSettings::firstOrCreate(
            ['shop_id' => $shop->id],
            [
                'email_mode' => ShopEmailSettings::EMAIL_MODE_GLOBAL,
                'provider' => ShopEmailSettings::PROVIDER_BREVO,
            ]
        );

        $newFromEmail = $fromEmail !== '' ? $fromEmail : null;
        $newFromName = $fromName !== '' ? $fromName : null;
        $newDomain = $domain !== '' ? $domain : null;

        $domainInfoChanged = $settings->from_email !== $newFromEmail
            || $settings->from_name !== $newFromName
            || $settings->domain !== $newDomain;
        if ($domainInfoChanged) {
            $settings->domain_verified = false;
        }

        $settings->email_mode = $mode;
        $settings->from_email = $newFromEmail;
        $settings->from_name = $newFromName;
        $settings->reply_to_email = $replyToEmail !== '' ? $replyToEmail : null;
        $settings->reply_to_name = $replyToName !== '' ? $replyToName : null;
        $settings->domain = $newDomain;
        $settings->save();

        $this->flashSet('success', 'Email settings saved.');

        return $this->redirect($response, '/admin/setup/email');
    }

    public function verifyDomain($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $settings = ShopEmailSettings::firstOrCreate(
            ['shop_id' => $shop->id],
            [
                'email_mode' => ShopEmailSettings::EMAIL_MODE_GLOBAL,
                'provider' => ShopEmailSettings::PROVIDER_BREVO,
            ]
        );
        $settings->domain_verified = false;
        $settings->save();

        $this->flashSet('info', 'Domain verification will be enabled soon. Emails will be sent using Cartly email until then.');

        return $this->redirect($response, '/admin/setup/email');
    }

    public function sendTestEmail($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $toEmail = trim((string)($data['test_email'] ?? ''));

        $validator = new Validator($data);
        $validator->rule('required', 'test_email')->message('Email address is required.');
        $validator->rule('email', 'test_email')->message('Enter a valid email address.');

        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());
        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', ['test_email' => $toEmail]);
            return $this->redirect($response, '/admin/setup/email');
        }

        $subject = 'Cartly test email';
        $htmlBody = '<p>This is a test email from your Cartly email settings.</p><p>If you received this, your configuration is working.</p>';
        $textBody = "This is a test email from your Cartly email settings.\n\nIf you received this, your configuration is working.";

        $sent = $this->transactionalMailService->send($shop, $toEmail, $toEmail, $subject, $htmlBody, $textBody);

        if ($sent) {
            $this->flashSet('success', 'Test email sent to ' . $toEmail . '.');
        } else {
            $this->flashSet('error', 'Failed to send test email. Daily limit may have been reached or the mail service is temporarily unavailable.');
        }

        return $this->redirect($response, '/admin/setup/email');
    }

    private function getShopOrRedirect(Response $response): Shop|Response
    {
        $this->startSession();
        $shopId = (int)($_SESSION['shop_id'] ?? 0);
        if ($shopId <= 0) {
            $this->flashSet('error', 'Shop settings are not available for this account.');
            return $this->redirect($response, '/admin/dashboard');
        }

        $shop = Shop::with('metadata')->find($shopId);
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

        $textSizeFields = ['base'];
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

    private function buildShopSchema(Shop $shop, $request, array $socialLinks): array
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
            'sameAs' => $this->buildSameAsLinks($socialLinks),
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

    /**
     * Build full OAuth redirect URIs for this shop (all domains) for Google and Facebook.
     * Paths: /auth/google/callback, /auth/facebook/callback.
     */
    private function buildOAuthRedirectUris(Shop $shop, string $scheme): array
    {
        $domains = $shop->domains()->pluck('domain')->filter()->map('strval')->values()->all();
        if ($domains === []) {
            return ['google' => [], 'facebook' => []];
        }
        $google = [];
        $facebook = [];
        foreach ($domains as $domain) {
            $base = $scheme . '://' . $domain;
            $google[] = $base . '/auth/google/callback';
            $facebook[] = $base . '/auth/facebook/callback';
        }
        return ['google' => $google, 'facebook' => $facebook];
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

    private function buildSameAsLinks(array $socialLinks): array
    {
        $links = [];
        $instagram = trim((string)($socialLinks['instagram'] ?? ''));
        $facebook = trim((string)($socialLinks['facebook'] ?? ''));
        $x = trim((string)($socialLinks['x'] ?? ''));
        $linkedin = trim((string)($socialLinks['linkedin'] ?? ''));
        $youtube = trim((string)($socialLinks['youtube'] ?? ''));
        $pinterest = trim((string)($socialLinks['pinterest'] ?? ''));
        $whatsapp = trim((string)($socialLinks['whatsapp'] ?? ''));
        if ($instagram !== '') {
            $links[] = $instagram;
        }
        if ($facebook !== '') {
            $links[] = $facebook;
        }
        if ($x !== '') {
            $links[] = $x;
        }
        if ($linkedin !== '') {
            $links[] = $linkedin;
        }
        if ($youtube !== '') {
            $links[] = $youtube;
        }
        if ($pinterest !== '') {
            $links[] = $pinterest;
        }
        if ($whatsapp !== '') {
            $links[] = $whatsapp;
        }
        return $links;
    }

    private function sanitizeHomeContent(array $input, array &$errors): array
    {
        $clean = [];

        $aboutTitle = $this->cleanText($input, ['about', 'title'], 255, 'home_content.about.title', $errors);
        $aboutDescription = $this->cleanText($input, ['about', 'description'], 500, 'home_content.about.description', $errors);
        if ($aboutTitle !== null || $aboutDescription !== null) {
            $clean['about'] = array_filter([
                'title' => $aboutTitle,
                'description' => $aboutDescription,
            ], static fn($value) => $value !== null);
        }

        $featuredTitle = $this->cleanText($input, ['featured_products', 'title'], 255, 'home_content.featured_products.title', $errors);
        $featuredSubtitle = $this->cleanText($input, ['featured_products', 'subtitle'], 255, 'home_content.featured_products.subtitle', $errors);
        if ($featuredTitle !== null || $featuredSubtitle !== null) {
            $clean['featured_products'] = array_filter([
                'title' => $featuredTitle,
                'subtitle' => $featuredSubtitle,
            ], static fn($value) => $value !== null);
        }

        $categoriesTitle = $this->cleanText($input, ['categories', 'title'], 255, 'home_content.categories.title', $errors);
        $categoriesSubtitle = $this->cleanText($input, ['categories', 'subtitle'], 255, 'home_content.categories.subtitle', $errors);
        if ($categoriesTitle !== null || $categoriesSubtitle !== null) {
            $clean['categories'] = array_filter([
                'title' => $categoriesTitle,
                'subtitle' => $categoriesSubtitle,
            ], static fn($value) => $value !== null);
        }

        $popularTitle = $this->cleanText($input, ['popular_new', 'title'], 255, 'home_content.popular_new.title', $errors);
        $popularSubtitle = $this->cleanText($input, ['popular_new', 'subtitle'], 255, 'home_content.popular_new.subtitle', $errors);
        if ($popularTitle !== null || $popularSubtitle !== null) {
            $clean['popular_new'] = array_filter([
                'title' => $popularTitle,
                'subtitle' => $popularSubtitle,
            ], static fn($value) => $value !== null);
        }

        $promoBadge = $this->cleanText($input, ['promo', 'badge'], 120, 'home_content.promo.badge', $errors);
        $promoTitle = $this->cleanText($input, ['promo', 'title'], 255, 'home_content.promo.title', $errors);
        $promoBody = $this->cleanText($input, ['promo', 'body'], 500, 'home_content.promo.body', $errors);
        $promoCtaText = $this->cleanText($input, ['promo', 'cta_text'], 120, 'home_content.promo.cta_text', $errors);
        $promoCtaLink = $this->cleanText($input, ['promo', 'cta_link'], 255, 'home_content.promo.cta_link', $errors);
        if ($promoBadge !== null || $promoTitle !== null || $promoBody !== null || $promoCtaText !== null || $promoCtaLink !== null) {
            $clean['promo'] = array_filter([
                'badge' => $promoBadge,
                'title' => $promoTitle,
                'body' => $promoBody,
                'cta_text' => $promoCtaText,
                'cta_link' => $promoCtaLink,
            ], static fn($value) => $value !== null);
        }

        $testimonialTitle = $this->cleanText($input, ['testimonials', 'title'], 255, 'home_content.testimonials.title', $errors);
        $testimonialSubtitle = $this->cleanText($input, ['testimonials', 'subtitle'], 255, 'home_content.testimonials.subtitle', $errors);
        $testimonialItems = $this->cleanTestimonials($input['testimonials']['items'] ?? [], $errors);
        if ($testimonialTitle !== null || $testimonialSubtitle !== null || !empty($testimonialItems)) {
            $clean['testimonials'] = array_filter([
                'title' => $testimonialTitle,
                'subtitle' => $testimonialSubtitle,
                'items' => !empty($testimonialItems) ? $testimonialItems : null,
            ], static fn($value) => $value !== null);
        }

        $newsletterTitle = $this->cleanText($input, ['newsletter', 'title'], 255, 'home_content.newsletter.title', $errors);
        $newsletterSubtitle = $this->cleanText($input, ['newsletter', 'subtitle'], 255, 'home_content.newsletter.subtitle', $errors);
        $newsletterCta = $this->cleanText($input, ['newsletter', 'cta_text'], 120, 'home_content.newsletter.cta_text', $errors);
        if ($newsletterTitle !== null || $newsletterSubtitle !== null || $newsletterCta !== null) {
            $clean['newsletter'] = array_filter([
                'title' => $newsletterTitle,
                'subtitle' => $newsletterSubtitle,
                'cta_text' => $newsletterCta,
            ], static fn($value) => $value !== null);
        }

        return $clean;
    }

    private function sanitizeFooterContent(array $input, array &$errors): array
    {
        $clean = [];

        $summary = $this->cleanText($input, ['summary'], 500, 'footer.summary', $errors);
        $legal = $this->cleanText($input, ['legal'], 255, 'footer.legal', $errors);
        if ($summary !== null) {
            $clean['summary'] = $summary;
        }
        if ($legal !== null) {
            $clean['legal'] = $legal;
        }

        $contactEmail = $this->cleanText($input, ['contact', 'email'], 255, 'footer.contact.email', $errors);
        $contactPhone = $this->cleanText($input, ['contact', 'phone'], 50, 'footer.contact.phone', $errors);
        if ($contactEmail !== null || $contactPhone !== null) {
            $clean['contact'] = array_filter([
                'email' => $contactEmail,
                'phone' => $contactPhone,
            ], static fn($value) => $value !== null);
        }

        $quickLinks = $this->cleanFooterLinks($input['links']['quick'] ?? [], 'footer.links.quick', $errors);
        $supportLinks = $this->cleanFooterLinks($input['links']['support'] ?? [], 'footer.links.support', $errors);
        if (!empty($quickLinks) || !empty($supportLinks)) {
            $clean['links'] = array_filter([
                'quick' => !empty($quickLinks) ? $quickLinks : null,
                'support' => !empty($supportLinks) ? $supportLinks : null,
            ], static fn($value) => $value !== null);
        }

        return $clean;
    }

    private function cleanFooterLinks(mixed $itemsInput, string $fieldPrefix, array &$errors): array
    {
        if (!is_array($itemsInput)) {
            return [];
        }

        $clean = [];
        foreach ($itemsInput as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = $this->cleanText($item, ['label'], 80, "{$fieldPrefix}.{$index}.label", $errors, true);
            $url = $this->cleanText($item, ['url'], 255, "{$fieldPrefix}.{$index}.url", $errors, true);
            $labelValue = $label ?? '';
            $urlValue = $url ?? '';
            if ($labelValue === '' && $urlValue === '') {
                continue;
            }
            if ($labelValue === '' || $urlValue === '') {
                $errors["{$fieldPrefix}.{$index}.pair"] = 'Link label and URL are required together.';
                continue;
            }
            $clean[] = [
                'label' => $labelValue,
                'url' => $urlValue,
            ];
        }

        return array_slice($clean, 0, 6);
    }

    private function cleanTestimonials(mixed $itemsInput, array &$errors): array
    {
        if (!is_array($itemsInput)) {
            return [];
        }

        $clean = [];
        foreach ($itemsInput as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $quote = $this->cleanText($item, ['quote'], 500, "home_content.testimonials.items.{$index}.quote", $errors);
            $name = $this->cleanText($item, ['name'], 255, "home_content.testimonials.items.{$index}.name", $errors);
            if ($quote === null && $name === null) {
                continue;
            }
            $clean[] = [
                'quote' => $quote ?? '',
                'name' => $name ?? '',
            ];
        }

        return array_slice($clean, 0, 10);
    }

    private function cleanText(array $input, array $path, int $maxLength, string $field, array &$errors, bool $allowEmpty = false): ?string
    {
        $value = $input;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        $value = trim((string)$value);
        if ($value === '') {
            return $allowEmpty ? '' : null;
        }

        if (strlen($value) > $maxLength) {
            $errors[$field] = 'Value is too long.';
        }

        return $value;
    }

    private function sanitizeSocialLinks(array $seo): array
    {
        $links = [
            'facebook' => trim((string)($seo['facebook'] ?? '')),
            'instagram' => trim((string)($seo['instagram'] ?? '')),
            'x' => trim((string)($seo['x'] ?? '')),
            'linkedin' => trim((string)($seo['linkedin'] ?? '')),
            'youtube' => trim((string)($seo['youtube'] ?? '')),
            'pinterest' => trim((string)($seo['pinterest'] ?? '')),
            'whatsapp' => trim((string)($seo['whatsapp'] ?? '')),
        ];

        return array_filter($links, static fn($value) => $value !== '');
    }

    private function sanitizeThirdParty(mixed $input, array &$errors): array
    {
        if (!is_array($input)) {
            return [];
        }

        $fields = [
            'head_html' => 50000,
            'body_start_html' => 50000,
            'body_end_html' => 50000,
        ];

        $sanitized = [];
        foreach ($fields as $field => $maxLength) {
            $value = $input[$field] ?? '';
            if (is_array($value) || is_object($value)) {
                $value = '';
            }
            $value = str_replace("\0", '', trim((string)$value));
            if ($value === '') {
                continue;
            }
            if (strlen($value) > $maxLength) {
                $errors["third_party.{$field}"] = 'Snippet is too long.';
                continue;
            }
            $sanitized[$field] = $value;
        }

        return $sanitized;
    }
}
