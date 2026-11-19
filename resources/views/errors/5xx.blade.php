<x-errors.page :code="isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500" :exception="$exception ?? null" />
