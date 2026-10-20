<?php
// Container health check: the front page must answer without a server error.
// (It needs the database, so an unreachable database makes the container unhealthy.)
$context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true, 'follow_location' => 0]]);
@file_get_contents('http://127.0.0.1:8080/', false, $context);
$status = isset($http_response_header[0]) && preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0], $m) ? (int) $m[1] : 0;
exit($status > 0 && $status < 500 ? 0 : 1);
