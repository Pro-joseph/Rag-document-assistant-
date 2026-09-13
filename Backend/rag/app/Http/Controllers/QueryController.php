<?php

namespace App\Http\Controllers;

use App\Exceptions\GroqUnavailableException;
use App\Http\Requests\QueryRequest;
use App\Http\Resources\QueryResource;
use App\Services\AnswerGenerator;
use App\Services\Retriever;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class QueryController extends Controller
{
    public function ask(QueryRequest $request, Retriever $retriever, AnswerGenerator $generator): QueryResource|JsonResponse
    {
        $question = $request->string('question')->toString();

        try {
            $hits = $retriever->search($question);
        } catch (RequestException|\Throwable $e) {
            Log::error('Query embedding failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'AI service temporarily unavailable. Please retry.',
                'code' => 'groq_unavailable',
                'sources' => [],
            ], 503);
        }

        try {
            $answer = $generator->generate($question, $hits);
        } catch (GroqUnavailableException $e) {
            return response()->json([
                'message' => 'AI service temporarily unavailable. Please retry.',
                'code' => 'groq_unavailable',
                'sources' => (new QueryResource(['answer' => '', 'sources' => $e->sources]))->toArray($request)['sources'],
            ], 503);
        }

        return new QueryResource(['answer' => $answer, 'sources' => $hits]);
    }
}
