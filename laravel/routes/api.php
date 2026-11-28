<?php
use GX\Applications\Applications;
use GX\Applications\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

function applicationSummaryFields(array $record): array
{
    return array_intersect_key($record, array_flip([
        'id', 'empresa', 'cargo', 'origem', 'etapa', 'data_envio', 'historico',
    ]));
}

function readApplicationsRegistry()
{
    try {
        return Storage::load();
    } catch (Throwable $error) {
        report($error);
        return response()->json(['error' => 'Não foi possível ler o catálogo local.'], 500);
    }
}

Route::get('/applications', function (Request $request) {
    $stage = $request->query('stage', 'all');
    $query = $request->query('q', '');
    $limitRaw = $request->query('limit', '20');
    $offsetRaw = $request->query('offset', '0');

    if (!is_string($stage) || !is_string($query) ||
        !is_string($limitRaw) || !is_string($offsetRaw)) {
        return response()->json(['error' => 'Filtros devem ser valores simples.'], 422);
    }

    $limit = filter_var($limitRaw, FILTER_VALIDATE_INT);
    $offset = filter_var($offsetRaw, FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 100 ||
        $offset === false || $offset < 0 || $offset > 5000) {
        return response()->json(['error' => 'Paginação inválida.'], 422);
    }

    $registry = readApplicationsRegistry();
    if ($registry instanceof \Illuminate\Http\JsonResponse) {
        return $registry;
    }

    try {
        $items = Applications::filter($registry, $stage, $query);
    } catch (InvalidArgumentException $error) {
        return response()->json(['error' => $error->getMessage()], 422);
    }

    $total = count($items);
    $next = $offset + $limit < $total ? $offset + $limit : null;
    return response()->json([
        'total' => $total,
        'pagination' => ['limit' => $limit, 'offset' => $offset, 'next_offset' => $next],
        'items' => array_map('applicationSummaryFields', array_slice($items, $offset, $limit)),
    ]);
});

Route::get('/applications/summary', function () {
    $registry = readApplicationsRegistry();
    if ($registry instanceof \Illuminate\Http\JsonResponse) {
        return $registry;
    }
    return response()->json(Applications::summary($registry));
});

Route::get('/applications/follow-ups', function (Request $request) {
    $date = $request->query('date', now()->toDateString());
    $daysRaw = $request->query('days', '14');
    $limitRaw = $request->query('limit', '20');
    if (!is_string($date) || !is_string($daysRaw) || !is_string($limitRaw)) {
        return response()->json(['error' => 'Parâmetros devem ser valores simples.'], 422);
    }
    $days = filter_var($daysRaw, FILTER_VALIDATE_INT);
    $limit = filter_var($limitRaw, FILTER_VALIDATE_INT);
    if ($days === false || $limit === false) {
        return response()->json(['error' => 'days e limit devem ser inteiros.'], 422);
    }
    $registry = readApplicationsRegistry();
    if ($registry instanceof \Illuminate\Http\JsonResponse) {
        return $registry;
    }
    try {
        return response()->json([
            'date' => $date,
            'items' => Applications::followUps($registry, $date, $days, $limit),
        ]);
    } catch (InvalidArgumentException $error) {
        return response()->json(['error' => $error->getMessage()], 422);
    }
});

Route::get('/applications/overdue', function (Request $request) {
    $date = $request->query('date', now()->toDateString());
    $daysRaw = $request->query('days', '14');
    if (!is_string($date) || !is_string($daysRaw)) {
        return response()->json(['error' => 'Parâmetros devem ser valores simples.'], 422);
    }
    $days = filter_var($daysRaw, FILTER_VALIDATE_INT);
    if ($days === false) {
        return response()->json(['error' => 'days deve ser inteiro.'], 422);
    }
    $registry = readApplicationsRegistry();
    if ($registry instanceof \Illuminate\Http\JsonResponse) {
        return $registry;
    }
    try {
        $items = Applications::overdue($registry, $date, $days);
        return response()->json([
            'date' => $date,
            'items' => array_map('applicationSummaryFields', $items),
        ]);
    } catch (InvalidArgumentException $error) {
        return response()->json(['error' => $error->getMessage()], 422);
    }
});

Route::get('/applications/{id}', function (string $id) {
    $registry = readApplicationsRegistry();
    if ($registry instanceof \Illuminate\Http\JsonResponse) {
        return $registry;
    }
    foreach ($registry as $record) {
        if ($record['id'] === $id) {
            return response()->json(applicationSummaryFields($record));
        }
    }
    return response()->json(['error' => 'Candidatura não encontrada.'], 404);
});
