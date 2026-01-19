<?php

namespace App\Controllers;

use App\Models\Package;
use App\Config\PackageConfig;
use Slim\Psr7\Response;
use Valitron\Validator;

class PackageController extends AppController
{
    /**
     * List all packages
     */
    public function index($request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $search = (string)($params['search'] ?? '');

        $sortMap = [
            'name' => 'name',
        ];

        $query = Package::query();
        if ($search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $pager = $this->paginationService()->paginate($query, $params, [
            'basePath' => '/admin/packages',
            'sortMap' => $sortMap,
            'search' => [
                'param' => 'search',
                'columns' => ['name'],
            ],
        ]);

        return $this->render($response, 'packages/list.twig', [
            'pager' => $pager,
            'featureDefinitions' => PackageConfig::features(),
        ]);
    }

    /**
     * Show create package form
     */
    public function create($request, Response $response): Response
    {
        $errors = $this->flashGet('errors', []);
        $data = $this->flashGet('old', []);
        $featureDefinitions = PackageConfig::features();
        $featureInput = $this->normalizeFeatureInput($data['features'] ?? [], $featureDefinitions);

        return $this->render($response, 'packages/create.twig', [
            'errors' => $errors,
            'data' => $data,
            'featureDefinitions' => $featureDefinitions,
            'featureInput' => $featureInput,
        ]);
    }

    /**
     * Store package
     */
    public function store($request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $packageData = $data['package'] ?? [];
        $featureDefinitions = PackageConfig::features();
        $featureInput = $this->normalizeFeatureInput($data['features'] ?? [], $featureDefinitions);
        $validator = new Validator($data);
        $validator->rule('required', 'package.name')->message('Package name is required');
        foreach ([1, 3, 6, 12] as $period) {
            $costKey = "package.cost_{$period}_month";
            $validator->rule('required', $costKey)->message('Valid cost is required');
            $validator->rule('numeric', $costKey)->message('Valid cost is required');
            $validator->rule('min', $costKey, 0.01)->message('Valid cost is required');
        }
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        $name = (string)($packageData['name'] ?? '');
        if ($name !== '' && Package::where('name', $name)->exists()) {
            $errors['package.name'] = 'A package with this name already exists';
        }

        foreach ($featureDefinitions as $key => $label) {
            $value = $featureInput[$key] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }
            $featureValidator = new Validator([$key => $value]);
            $featureValidator->rule('numeric', $key)->message($label . ' must be a non-negative number');
            $featureValidator->rule('min', $key, 0)->message($label . ' must be a non-negative number');
            if (!$featureValidator->validate()) {
                $featureErrors = $this->formatValitronErrors($featureValidator->errors());
                if (isset($featureErrors[$key])) {
                    $errors["features.{$key}"] = $featureErrors[$key];
                }
            }
        }

        if ($errors) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/packages/create');
        }

        $features = $this->buildFeatures($featureInput, $featureDefinitions);

        Package::create([
            'name' => $name,
            'cost_1_month' => $packageData['cost_1_month'] ?? 0,
            'cost_3_month' => $packageData['cost_3_month'] ?? 0,
            'cost_6_month' => $packageData['cost_6_month'] ?? 0,
            'cost_12_month' => $packageData['cost_12_month'] ?? 0,
            'features' => $features,
            'active' => ($packageData['active'] ?? '0') === '1',
        ]);

        $this->flashSet('success', 'Package created successfully.');

        return $this->redirect($response, '/admin/packages');
    }

    /**
     * Show edit package form
     */
    public function edit($request, Response $response): Response
    {
        $packageId = $request->getAttribute('id');
        $package = Package::find($packageId);

        if (!$package) {
            return $response->withStatus(404);
        }

        $errors = $this->flashGet('errors', []);
        $data = $this->flashGet('old', []);
        $featureDefinitions = PackageConfig::features();
        $featureInput = $this->normalizeFeatureInput(
            $data['features'] ?? ($package->features ?? []),
            $featureDefinitions
        );

        return $this->render($response, 'packages/edit.twig', [
            'package' => $package,
            'errors' => $errors,
            'data' => $data,
            'featureDefinitions' => $featureDefinitions,
            'featureInput' => $featureInput,
        ]);
    }

    /**
     * Update package
     */
    public function update($request, Response $response): Response
    {
        $packageId = $request->getAttribute('id');
        $package = Package::find($packageId);

        if (!$package) {
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        $packageData = $data['package'] ?? [];
        $featureDefinitions = PackageConfig::features();
        $featureInput = $this->normalizeFeatureInput($data['features'] ?? [], $featureDefinitions);
        $validator = new Validator($data);
        $validator->rule('required', 'package.name')->message('Package name is required');
        foreach ([1, 3, 6, 12] as $period) {
            $costKey = "package.cost_{$period}_month";
            $validator->rule('required', $costKey)->message('Valid cost is required');
            $validator->rule('numeric', $costKey)->message('Valid cost is required');
            $validator->rule('min', $costKey, 0.01)->message('Valid cost is required');
        }
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        $name = (string)($packageData['name'] ?? '');
        if ($name !== '') {
            $query = Package::where('name', $name)->where('id', '!=', $package->id);
            if ($query->exists()) {
                $errors['package.name'] = 'A package with this name already exists';
            }
        }

        foreach ($featureDefinitions as $key => $label) {
            $value = $featureInput[$key] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }
            $featureValidator = new Validator([$key => $value]);
            $featureValidator->rule('numeric', $key)->message($label . ' must be a non-negative number');
            $featureValidator->rule('min', $key, 0)->message($label . ' must be a non-negative number');
            if (!$featureValidator->validate()) {
                $featureErrors = $this->formatValitronErrors($featureValidator->errors());
                if (isset($featureErrors[$key])) {
                    $errors["features.{$key}"] = $featureErrors[$key];
                }
            }
        }

        if ($errors) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/packages/' . $package->id . '/edit');
        }

        $features = $this->buildFeatures($featureInput, $featureDefinitions);

        $package->update([
            'name' => $name,
            'cost_1_month' => $packageData['cost_1_month'] ?? 0,
            'cost_3_month' => $packageData['cost_3_month'] ?? 0,
            'cost_6_month' => $packageData['cost_6_month'] ?? 0,
            'cost_12_month' => $packageData['cost_12_month'] ?? 0,
            'features' => $features,
            'active' => ($packageData['active'] ?? '0') === '1',
        ]);

        $this->flashSet('success', 'Package updated successfully.');

        return $this->redirect($response, '/admin/packages');
    }

    /**
     * Delete package
     */
    public function delete($request, Response $response): Response
    {
        $packageId = $request->getAttribute('id');
        $package = Package::find($packageId);

        if (!$package) {
            return $response->withStatus(404);
        }

        $package->delete();

        $this->flashSet('success', 'Package deleted successfully.');

        return $this->redirect($response, '/admin/packages');
    }

    private function normalizeFeatureInput(array $input, array $featureDefinitions): array
    {
        $normalized = [];
        foreach ($featureDefinitions as $key => $label) {
            $normalized[$key] = $input[$key] ?? '';
        }
        return $normalized;
    }

    private function buildFeatures(array $featureInput, array $featureDefinitions): array
    {
        $features = [];
        foreach ($featureDefinitions as $key => $label) {
            $value = $featureInput[$key] ?? '';
            if ($value === '' || $value === null) {
                $features[$key] = 0;
            } else {
                $features[$key] = (int)$value;
            }
        }
        return $features;
    }

}
