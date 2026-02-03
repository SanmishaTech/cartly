<?php

namespace App\Controllers\Admin;

use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\Shop;
use App\Services\LocalStorageService;
use Slim\Psr7\Response;
use Valitron\Validator;

class PageController extends AppController
{
    private const STATUS_OPTIONS = ['draft', 'published'];
    private const BLOCK_TYPES = ['text', 'image_text', 'bullets', 'image', 'faq'];
    private const IMAGE_TEXT_VARIANTS = ['image_left', 'image_right'];
    private const BLOCK_KEYS = ['id', 'type', 'variant', 'data'];

    public function index($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $params = $request->getQueryParams();
        $search = (string)($params['search'] ?? '');

        $sortMap = [
            'title' => 'title',
            'slug' => 'slug',
            'status' => 'status',
            'menu' => 'show_in_menu',
            'updated' => 'updated_at',
        ];

        $query = Page::where('shop_id', $shop->id)
            ->where('type', 'standard');

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%');
            });
        }

        $pager = $this->paginationService()->paginate($query, $params, [
            'basePath' => '/admin/pages',
            'sortMap' => $sortMap,
            'search' => [
                'param' => 'search',
                'columns' => ['title', 'slug'],
            ],
        ]);

        return $this->render($response, 'pages/list.twig', [
            'pager' => $pager,
        ]);
    }

    public function create($request, Response $response): Response
    {
        $errors = $this->flashGet('errors', []);
        $data = $this->flashGet('old', []);
        $blocks = $this->normalizeBlocks($data['content_json']['blocks'] ?? []);

        return $this->render($response, 'pages/create.twig', [
            'errors' => $errors,
            'data' => $data,
            'blocks' => $blocks,
            'seo_metadata' => null,
        ]);
    }

    public function store($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $pageInput = (array)($data['page'] ?? []);
        $blocksInput = $data['content_json']['blocks'] ?? [];
        $uploadErrors = [];
        $blocksInput = $this->applyBlockUploads($blocksInput, $request, $shop, $uploadErrors);

        $validator = new Validator($data);
        $validator->rule('required', 'page.title')->message('Page title is required.');
        $validator->rule('lengthMax', 'page.title', 255)->message('Page title is too long.');
        $validator->rule('in', 'page.status', self::STATUS_OPTIONS)->message('Select a valid status.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());
        $errors = array_merge($uploadErrors, $errors);
        $seoInput = (array)($data['seo'] ?? []);
        
        // Handle OG image file upload
        $uploads = $request->getUploadedFiles();
        $ogImageFile = $uploads['og_image'] ?? null;
        if ($ogImageFile) {
            if (!$this->validateImageUpload($ogImageFile, 'seo.og_image', $errors)) {
                // Validation failed, error already added
            } elseif ($ogImageFile->getError() === UPLOAD_ERR_OK) {
                $storage = new LocalStorageService();
                try {
                    $seoInput['og_image'] = $storage->storeShopImageFit($ogImageFile, $shop->id, 'og', 1200, 630);
                } catch (\RuntimeException $exception) {
                    $errors['seo.og_image'] = $exception->getMessage();
                }
            }
        }
        
        $errors = array_merge($errors, $this->validateSeoInput($seoInput));

        $title = trim((string)($pageInput['title'] ?? ''));
        $slug = $this->slugify($title);
        if ($slug === '') {
            $errors['page.slug'] = 'Slug is required.';
        } elseif ($this->isReservedSlug($slug)) {
            $errors['page.slug'] = 'This slug is reserved.';
        } elseif (Page::where('shop_id', $shop->id)->where('slug', $slug)->exists()) {
            $errors['page.slug'] = 'Slug already exists. Choose another.';
        }

        $blocks = $this->sanitizeBlocks(is_array($blocksInput) ? $blocksInput : [], $errors);

        if (!empty($errors)) {
            $data['content_json']['blocks'] = $blocksInput;
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/pages/create');
        }

        $page = Page::create([
            'shop_id' => $shop->id,
            'slug' => $slug,
            'title' => $title,
            'type' => 'standard',
            'content_json' => $blocks,
            'status' => $pageInput['status'] ?? 'draft',
        ]);
        $this->syncFaqSchema($page, $blocks);
        $this->saveSeoMetadata($page, $seoInput);

        $this->flashSet('success', 'Page created successfully.');

        return $this->redirect($response, '/admin/pages');
    }

    public function edit($request, Response $response): Response
    {
        $pageId = (int)$request->getAttribute('id');
        $page = Page::find($pageId);
        if (!$page) {
            return $response->withStatus(404);
        }
        if ($page->type !== 'standard') {
            return $response->withStatus(403);
        }

        $errors = $this->flashGet('errors', []);
        $data = $this->flashGet('old', []);
        $blocks = $this->normalizeBlocks($data['content_json']['blocks'] ?? ($page->content_json ?? []));
        $seoMetadata = SeoMetadata::where('entity_type', 'page')
            ->where('entity_id', $page->id)
            ->first();

        return $this->render($response, 'pages/edit.twig', [
            'page' => $page,
            'errors' => $errors,
            'data' => $data,
            'blocks' => $blocks,
            'seo_metadata' => $seoMetadata,
        ]);
    }

    public function update($request, Response $response): Response
    {
        $pageId = (int)$request->getAttribute('id');
        $page = Page::find($pageId);
        if (!$page) {
            return $response->withStatus(404);
        }
        if ($page->type !== 'standard') {
            return $response->withStatus(403);
        }

        $data = (array)$request->getParsedBody();
        $shop = Shop::find($page->shop_id);
        if (!$shop) {
            return $response->withStatus(404);
        }
        $pageInput = (array)($data['page'] ?? []);
        $blocksInput = $data['content_json']['blocks'] ?? [];
        $uploadErrors = [];
        $blocksInput = $this->applyBlockUploads($blocksInput, $request, $shop, $uploadErrors);

        $validator = new Validator($data);
        $validator->rule('required', 'page.title')->message('Page title is required.');
        $validator->rule('lengthMax', 'page.title', 255)->message('Page title is too long.');
        $validator->rule('in', 'page.status', self::STATUS_OPTIONS)->message('Select a valid status.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());
        $errors = array_merge($uploadErrors, $errors);
        $seoInput = (array)($data['seo'] ?? []);
        
        // Handle OG image file upload
        $uploads = $request->getUploadedFiles();
        $ogImageFile = $uploads['og_image'] ?? null;
        if ($ogImageFile) {
            if (!$this->validateImageUpload($ogImageFile, 'seo.og_image', $errors)) {
                // Validation failed, error already added
            } elseif ($ogImageFile->getError() === UPLOAD_ERR_OK) {
                $storage = new LocalStorageService();
                try {
                    $seoInput['og_image'] = $storage->storeShopImageFit($ogImageFile, $shop->id, 'og', 1200, 630);
                } catch (\RuntimeException $exception) {
                    $errors['seo.og_image'] = $exception->getMessage();
                }
            }
        } else {
            // Preserve existing OG image if no new file is uploaded
            $existingSeo = SeoMetadata::where('entity_type', 'page')
                ->where('entity_id', $page->id)
                ->first();
            if ($existingSeo && $existingSeo->og_image) {
                $seoInput['og_image'] = $existingSeo->og_image;
            }
        }
        
        $errors = array_merge($errors, $this->validateSeoInput($seoInput));

        $title = trim((string)($pageInput['title'] ?? ''));
        $slug = $this->slugify($title);
        if ($slug === '') {
            $errors['page.slug'] = 'Slug is required.';
        } elseif ($this->isReservedSlug($slug)) {
            $errors['page.slug'] = 'This slug is reserved.';
        } elseif (Page::where('shop_id', $page->shop_id)->where('slug', $slug)->where('id', '!=', $page->id)->exists()) {
            $errors['page.slug'] = 'Slug already exists. Choose another.';
        }

        $blocks = $this->sanitizeBlocks(is_array($blocksInput) ? $blocksInput : [], $errors);

        if (!empty($errors)) {
            $data['content_json']['blocks'] = $blocksInput;
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/pages/' . $page->id . '/edit');
        }

        $page->update([
            'slug' => $slug,
            'title' => $title,
            'content_json' => $blocks,
            'status' => $pageInput['status'] ?? 'draft',
        ]);
        $this->syncFaqSchema($page, $blocks);
        $this->saveSeoMetadata($page, $seoInput);

        $this->flashSet('success', 'Page updated successfully.');

        return $this->redirect($response, '/admin/pages');
    }

    public function publish($request, Response $response): Response
    {
        return $this->setStatus($request, $response, 'published');
    }

    public function unpublish($request, Response $response): Response
    {
        return $this->setStatus($request, $response, 'draft');
    }

    private function setStatus($request, Response $response, string $status): Response
    {
        $pageId = (int)$request->getAttribute('id');
        $page = Page::find($pageId);
        if (!$page) {
            return $response->withStatus(404);
        }
        if ($page->type !== 'standard') {
            return $response->withStatus(403);
        }
        if (!in_array($status, self::STATUS_OPTIONS, true)) {
            return $response->withStatus(400);
        }

        $page->update(['status' => $status]);
        $this->flashSet('success', $status === 'published' ? 'Page published.' : 'Page unpublished.');

        return $this->redirect($response, '/admin/pages');
    }

    private function normalizeBlocks(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $blocks = [];
        foreach ($input as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = trim((string)($block['type'] ?? ''));
            if (!in_array($type, self::BLOCK_TYPES, true)) {
                continue;
            }
            $data = $block['data'] ?? [];
            if (!is_array($data)) {
                $data = [];
            }
            if (empty($data)) {
                $data = $this->legacyBlockToData($block, $type);
            }
            $blocks[] = [
                'id' => $block['id'] ?? null,
                'type' => $type,
                'variant' => $block['variant'] ?? null,
                'data' => $data,
            ];
        }

        return $blocks;
    }

    private function sanitizeBlocks(array $blocksInput, array &$errors): array
    {
        $clean = [];
        $seenIds = [];

        foreach ($blocksInput as $index => $blockInput) {
            if (!is_array($blockInput)) {
                continue;
            }
            $type = trim((string)($blockInput['type'] ?? ''));
            if (!in_array($type, self::BLOCK_TYPES, true)) {
                $errors["content_json.blocks.{$index}.type"] = 'Select a valid block type.';
                continue;
            }

            $id = trim((string)($blockInput['id'] ?? ''));
            if ($id === '') {
                $errors["content_json.blocks.{$index}.id"] = 'Block ID is required.';
            } elseif (isset($seenIds[$id])) {
                $errors["content_json.blocks.{$index}.id"] = 'Block ID must be unique.';
            } else {
                $seenIds[$id] = true;
            }

            $allowedKeys = $type === 'image_text'
                ? ['id', 'type', 'variant', 'data']
                : ['id', 'type', 'data'];
            $unknownKeys = array_diff(array_keys($blockInput), $allowedKeys);
            if (!empty($unknownKeys)) {
                $errors["content_json.blocks.{$index}.unknown"] = 'Unknown block fields detected.';
            }

            $dataInput = $blockInput['data'] ?? [];
            if (!is_array($dataInput)) {
                $errors["content_json.blocks.{$index}.data"] = 'Block data is required.';
                $dataInput = [];
            }

            $block = [
                'id' => $id,
                'type' => $type,
                'data' => [],
            ];

            if ($type === 'text') {
                $this->rejectUnknownDataKeys($dataInput, ['html'], $errors, "content_json.blocks.{$index}.data");
                $html = $this->cleanHtml($dataInput['html'] ?? null, "content_json.blocks.{$index}.data.html", $errors);
                if ($html === null) {
                    $errors["content_json.blocks.{$index}.data.html"] = 'Text content is required.';
                } else {
                    $block['data']['html'] = $html;
                }
            }

            if ($type === 'image_text') {
                $variant = trim((string)($blockInput['variant'] ?? ''));
                if ($variant === '' || !in_array($variant, self::IMAGE_TEXT_VARIANTS, true)) {
                    $errors["content_json.blocks.{$index}.variant"] = 'Select a valid layout.';
                } else {
                    $block['variant'] = $variant;
                }

                $this->rejectUnknownDataKeys($dataInput, ['image', 'alt', 'title', 'html'], $errors, "content_json.blocks.{$index}.data");
                $image = $this->cleanText($dataInput['image'] ?? null, 255, "content_json.blocks.{$index}.data.image", $errors, true);
                $alt = $this->cleanText($dataInput['alt'] ?? null, 150, "content_json.blocks.{$index}.data.alt", $errors, true);
                $title = $this->cleanText($dataInput['title'] ?? null, 255, "content_json.blocks.{$index}.data.title", $errors);
                $html = $this->cleanHtml($dataInput['html'] ?? null, "content_json.blocks.{$index}.data.html", $errors);

                if ($image === null || $image === '') {
                    $errors["content_json.blocks.{$index}.data.image"] = 'Image path is required.';
                }
                if ($alt === null || $alt === '') {
                    $errors["content_json.blocks.{$index}.data.alt"] = 'Alt text is required.';
                }
                if ($html === null) {
                    $errors["content_json.blocks.{$index}.data.html"] = 'Text content is required.';
                }

                $block['data'] = [
                    'image' => $image ?? '',
                    'alt' => $alt ?? '',
                    'title' => $title,
                    'html' => $html ?? '',
                ];
            }

            if ($type === 'bullets') {
                $this->rejectUnknownDataKeys($dataInput, ['title', 'items'], $errors, "content_json.blocks.{$index}.data");
                $title = $this->cleanText($dataInput['title'] ?? null, 255, "content_json.blocks.{$index}.data.title", $errors);
                $itemsInput = $dataInput['items'] ?? [];
                $items = [];
                if (is_array($itemsInput)) {
                    foreach ($itemsInput as $item) {
                        $itemText = trim((string)$item);
                        if ($itemText !== '') {
                            $items[] = $itemText;
                        }
                    }
                }
                if (count($items) < 1) {
                    $errors["content_json.blocks.{$index}.data.items"] = 'Add at least one bullet item.';
                } elseif (count($items) > 10) {
                    $errors["content_json.blocks.{$index}.data.items"] = 'Bullets can have up to 10 items.';
                }
                $block['data'] = [
                    'title' => $title,
                    'items' => $items,
                ];
            }

            if ($type === 'image') {
                $this->rejectUnknownDataKeys($dataInput, ['image', 'alt', 'caption'], $errors, "content_json.blocks.{$index}.data");
                $image = $this->cleanText($dataInput['image'] ?? null, 255, "content_json.blocks.{$index}.data.image", $errors, true);
                $alt = $this->cleanText($dataInput['alt'] ?? null, 150, "content_json.blocks.{$index}.data.alt", $errors, true);
                $caption = $this->cleanText($dataInput['caption'] ?? null, 255, "content_json.blocks.{$index}.data.caption", $errors);

                if ($image === null || $image === '') {
                    $errors["content_json.blocks.{$index}.data.image"] = 'Image path is required.';
                }
                if ($alt === null || $alt === '') {
                    $errors["content_json.blocks.{$index}.data.alt"] = 'Alt text is required.';
                }

                $block['data'] = [
                    'image' => $image ?? '',
                    'alt' => $alt ?? '',
                    'caption' => $caption,
                ];
            }

            if ($type === 'faq') {
                $this->rejectUnknownDataKeys($dataInput, ['title', 'items'], $errors, "content_json.blocks.{$index}.data");
                $title = $this->cleanText($dataInput['title'] ?? null, 255, "content_json.blocks.{$index}.data.title", $errors);
                $itemsInput = $dataInput['items'] ?? [];
                $items = [];
                if (is_array($itemsInput)) {
                    foreach ($itemsInput as $itemIndex => $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $unknownFaqKeys = array_diff(array_keys($item), ['question', 'answer']);
                        if (!empty($unknownFaqKeys)) {
                            $errors["content_json.blocks.{$index}.data.items.{$itemIndex}.unknown"] = 'Unknown FAQ fields detected.';
                            continue;
                        }
                        $question = $this->cleanText($item['question'] ?? null, 255, "content_json.blocks.{$index}.data.items.{$itemIndex}.question", $errors, true);
                        $answer = $this->cleanHtml($item['answer'] ?? null, "content_json.blocks.{$index}.data.items.{$itemIndex}.answer", $errors);
                        if ($question === null || $question === '' || $answer === null || $answer === '') {
                            $errors["content_json.blocks.{$index}.data.items.{$itemIndex}.pair"] = 'Question and answer are required.';
                            continue;
                        }
                        $items[] = [
                            'question' => $question,
                            'answer' => $answer,
                        ];
                    }
                }
                if (count($items) < 1) {
                    $errors["content_json.blocks.{$index}.data.items"] = 'Add at least one FAQ item.';
                } elseif (count($items) > 15) {
                    $errors["content_json.blocks.{$index}.data.items"] = 'FAQ can have up to 15 items.';
                }
                $block['data'] = [
                    'title' => $title,
                    'items' => $items,
                ];
            }

            $clean[] = $block;
        }

        return $clean;
    }

    private function applyBlockUploads(mixed $blocksInput, $request, Shop $shop, array &$errors): array
    {
        if (!is_array($blocksInput)) {
            return [];
        }

        $uploads = $request->getUploadedFiles();
        $blockImages = $uploads['block_images'] ?? [];
        if (!is_array($blockImages)) {
            return $blocksInput;
        }

        $storage = new LocalStorageService();
        foreach ($blocksInput as $index => &$blockInput) {
            if (!is_array($blockInput)) {
                continue;
            }
            $type = trim((string)($blockInput['type'] ?? ''));
            if (!in_array($type, ['image', 'image_text'], true)) {
                continue;
            }
            $blockId = trim((string)($blockInput['id'] ?? ''));
            if ($blockId === '') {
                continue;
            }
            $file = $blockImages[$index] ?? ($blockImages[$blockId] ?? null);
            if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (!$this->validateImageUpload($file, "content_json.blocks.{$index}.data.image", $errors)) {
                continue;
            }

            try {
                $path = $storage->storeShopPageImage($file, $shop->id, 'page_' . $blockId, 1600, 900);
                $blockInput['data'] = is_array($blockInput['data'] ?? null) ? $blockInput['data'] : [];
                $blockInput['data']['image'] = $path;
            } catch (\RuntimeException $exception) {
                $errors["content_json.blocks.{$index}.data.image"] = $exception->getMessage();
            }
        }
        unset($blockInput);

        return $blocksInput;
    }

    private function cleanText(mixed $value, int $maxLength, string $field, array &$errors, bool $required = false): ?string
    {
        if ($value === null) {
            return $required ? '' : null;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return $required ? '' : null;
        }
        if (strlen($value) > $maxLength) {
            $errors[$field] = 'Value is too long.';
            return null;
        }
        return $value;
    }

    private function cleanLongText(mixed $value, int $maxLength, string $field, array &$errors, bool $required = false): ?string
    {
        if ($value === null) {
            return $required ? '' : null;
        }
        $value = str_replace("\0", '', trim((string)$value));
        if ($value === '') {
            return $required ? '' : null;
        }
        if (strlen($value) > $maxLength) {
            $errors[$field] = 'Value is too long.';
            return null;
        }
        return $value;
    }

    private function cleanHtml(mixed $value, string $field, array &$errors, bool $required = false): ?string
    {
        $html = $this->cleanLongText($value, 50000, $field, $errors, $required);
        if ($html === null) {
            return null;
        }

        if (preg_match('/<\s*h1\b/i', $html)) {
            $errors[$field] = 'H1 headings are not allowed on pages.';
            return null;
        }

        $html = preg_replace('/\sstyle=("|\')(.*?)\1/i', '', $html);
        $html = preg_replace('/\sstyle=([^"\'][^\s>]*)/i', '', $html);

        return $html;
    }

    private function validateImageUpload($file, string $field, array &$errors): bool
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $errors[$field] = 'Upload failed.';
            return false;
        }
        if ($file->getSize() > 3 * 1024 * 1024) {
            $errors[$field] = 'Image must be less than 3MB.';
            return false;
        }
        $mimeType = (string)$file->getClientMediaType();
        $allowed = ['image/png', 'image/jpeg', 'image/webp'];
        if (!in_array($mimeType, $allowed, true)) {
            $errors[$field] = 'Only PNG, JPG, or WebP images are allowed.';
            return false;
        }
        return true;
    }

    private function rejectUnknownDataKeys(array $data, array $allowed, array &$errors, string $fieldPrefix): void
    {
        $unknown = array_diff(array_keys($data), $allowed);
        if (!empty($unknown)) {
            $errors["{$fieldPrefix}.unknown"] = 'Unknown block data fields detected.';
        }
    }

    private function legacyBlockToData(array $block, string $type): array
    {
        if ($type === 'text') {
            return ['html' => $block['html'] ?? ''];
        }
        if ($type === 'image_text') {
            return [
                'image' => $block['image'] ?? '',
                'alt' => $block['alt'] ?? '',
                'title' => $block['title'] ?? null,
                'html' => $block['html'] ?? '',
            ];
        }
        if ($type === 'bullets') {
            return [
                'title' => $block['title'] ?? null,
                'items' => $block['items'] ?? [],
            ];
        }
        if ($type === 'image') {
            return [
                'image' => $block['image'] ?? '',
                'alt' => $block['alt'] ?? '',
                'caption' => $block['caption'] ?? null,
            ];
        }
        if ($type === 'faq') {
            return [
                'title' => $block['title'] ?? null,
                'items' => $block['items'] ?? [],
            ];
        }
        return [];
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string)$value, '-');
    }

    private function isReservedSlug(string $slug): bool
    {
        $reserved = [
            'admin',
            'login',
            'logout',
            'media',
            'assets',
            'products',
            'categories',
            'cart',
            'checkout',
            'account',
        ];
        return in_array($slug, $reserved, true);
    }

    private function syncFaqSchema(Page $page, array $blocks): void
    {
        $faqItems = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || ($block['type'] ?? '') !== 'faq') {
                continue;
            }
            $items = $block['data']['items'] ?? [];
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $question = trim((string)($item['question'] ?? ''));
                $answer = trim((string)($item['answer'] ?? ''));
                if ($question === '' || $answer === '') {
                    continue;
                }
                $faqItems[] = [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $answer,
                    ],
                ];
            }
        }

        $record = SeoMetadata::firstOrNew([
            'entity_type' => 'page',
            'entity_id' => $page->id,
        ]);

        if (empty($faqItems)) {
            if ($record->exists) {
                $record->schema_json = null;
                $record->save();
            }
            return;
        }

        $record->schema_json = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqItems,
        ];
        $record->save();
    }

    private function validateSeoInput(array $seoInput): array
    {
        $validator = new Validator(['seo' => $seoInput]);
        $validator->rule('lengthMax', 'seo.seo_title', 255)->message('SEO title is too long.');
        $validator->rule('lengthMax', 'seo.seo_description', 500)->message('SEO description is too long.');
        $validator->rule('lengthMax', 'seo.seo_keywords', 255)->message('SEO keywords are too long.');
        $validator->rule('lengthMax', 'seo.canonical_url', 255)->message('Canonical URL is too long.');
        $validator->rule('lengthMax', 'seo.og_title', 255)->message('OG title is too long.');
        $validator->rule('lengthMax', 'seo.og_description', 500)->message('OG description is too long.');
        $validator->rule('lengthMax', 'seo.og_image', 255)->message('OG image path is too long.');
        $validator->rule('url', 'seo.canonical_url')->message('Enter a valid canonical URL.');

        return $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());
    }

    private function saveSeoMetadata(Page $page, array $seoInput): void
    {
        $record = SeoMetadata::firstOrNew([
            'entity_type' => 'page',
            'entity_id' => $page->id,
        ]);

        $record->seo_title = $seoInput['seo_title'] ?? null;
        $record->seo_description = $seoInput['seo_description'] ?? null;
        $record->seo_keywords = $seoInput['seo_keywords'] ?? null;
        $record->canonical_url = $seoInput['canonical_url'] ?? null;
        $record->og_title = $seoInput['og_title'] ?? null;
        $record->og_description = $seoInput['og_description'] ?? null;
        $record->og_image = $seoInput['og_image'] ?? null;
        $record->save();
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
}
