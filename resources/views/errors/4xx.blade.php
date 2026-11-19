<x-errors.page :code="isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400" :exception="$exception ?? null" />
