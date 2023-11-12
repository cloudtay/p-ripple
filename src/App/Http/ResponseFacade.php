<?php

namespace App\Http;

class ResponseFacade
{
    /**
     * @param string $body
     * @return Response
     */
    public static function content(string $body): Response
    {
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        return new Response(200, $headers, $body);
    }

    /**
     * @param array $body
     * @return Response
     */
    public static function json(array $body): Response
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        return new Response(200, $headers, $body);
    }
}
