<?php

namespace App\Http\Controllers\Api\V1\Hris;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\JobLevel;
use App\Models\JobTitle;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HrisMasterDataController extends Controller
{
    public function index(Request $request, string $resource): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $modelClass = $config['model'];

        $query = $modelClass::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($config, $search) {
                foreach ($config['search'] as $column) {
                    $q->orWhere($column, 'ilike', "%{$search}%");
                }
            });
        }

        $query->orderBy($config['sort'], $config['direction'] ?? 'asc');

        if ($request->boolean('paginate')) {
            $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

            return response()->json($query->paginate($perPage));
        }

        return response()->json($query->get());
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $data = $this->validatedData($request, $config);
        $modelClass = $config['model'];

        $data['id'] = (string) Str::uuid();
        $item = $modelClass::create($data);

        return response()->json($item, 201);
    }

    public function show(string $resource, string $id): JsonResponse
    {
        return response()->json($this->findItem($resource, $id));
    }

    public function update(Request $request, string $resource, string $id): JsonResponse
    {
        $config = $this->resourceConfig($resource);
        $item = $this->findItem($resource, $id);
        $data = $this->validatedData($request, $config, true);

        if (empty($data)) {
            return response()->json(['message' => 'No data to update'], 422);
        }

        $item->update($data);

        return response()->json($item->fresh());
    }

    public function destroy(string $resource, string $id): JsonResponse
    {
        $item = $this->findItem($resource, $id);
        $item->delete();

        return response()->json(null, 204);
    }

    protected function resourceConfig(string $resource): array
    {
        $resources = $this->resources();

        abort_unless(isset($resources[$resource]), 404, 'Master data resource not found.');

        return $resources[$resource];
    }

    protected function findItem(string $resource, string $id): Model
    {
        $config = $this->resourceConfig($resource);
        $modelClass = $config['model'];

        return $modelClass::findOrFail($id);
    }

    protected function validatedData(Request $request, array $config, bool $partial = false): array
    {
        $rules = $config['rules'];

        if ($partial) {
            $rules = collect($rules)
                ->map(fn ($ruleSet) => array_merge(['sometimes'], $ruleSet))
                ->all();
        }

        $data = $request->validate($rules);

        if (array_key_exists('used', $data)) {
            $data['used'] = (bool) $data['used'];
        }

        if (array_key_exists('fixed', $data)) {
            $data['fixed'] = (bool) $data['fixed'];
        }

        return $data;
    }

    protected function resources(): array
    {
        return [
            'job-levels' => [
                'model' => JobLevel::class,
                'search' => ['code', 'name'],
                'rules' => [
                    'code' => ['required', 'string', 'max:7'],
                    'name' => ['required', 'string', 'max:255'],
                    'parent_id' => ['nullable', 'uuid'],
                ],
                'sort' => 'code',
            ],
            'job-titles' => [
                'model' => JobTitle::class,
                'search' => ['code', 'name'],
                'rules' => [
                    'code' => ['required', 'string', 'max:9'],
                    'name' => ['required', 'string', 'max:255'],
                    'job_level_id' => ['nullable', 'uuid'],
                ],
                'sort' => 'code',
            ],
            'contracts' => [
                'model' => Contract::class,
                'search' => ['letterNumber', 'subject'],
                'rules' => [
                    'type' => ['required', 'string', 'max:1'],
                    'letterNumber' => ['required', 'string', 'max:27'],
                    'subject' => ['required', 'string', 'max:255'],
                    'description' => ['nullable', 'string', 'max:255'],
                    'startDate' => ['required', 'date'],
                    'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
                    'signedDate' => ['required', 'date'],
                    'tags' => ['nullable', 'array'],
                    'used' => ['boolean'],
                ],
                'sort' => 'signedDate',
                'direction' => 'desc',
            ],
            'salary-components' => [
                'model' => SalaryComponent::class,
                'search' => ['code', 'name'],
                'rules' => [
                    'code' => ['required', 'string', 'max:7'],
                    'name' => ['required', 'string', 'max:255'],
                    'state' => ['required', Rule::in(['E', 'D', 'A'])],
                    'fixed' => ['boolean'],
                ],
                'sort' => 'code',
            ],
        ];
    }
}
