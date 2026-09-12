<?php

namespace App\Http\Controllers;

use App\Http\Requests\QueryRequest;
use App\Http\Resources\QueryResource;
use App\Services\AnswerGenerator;
use App\Services\Retriever;

class QueryController extends Controller
{
    public function ask(QueryRequest $request, Retriever $retriever, AnswerGenerator $generator): QueryResource
    {
        $hits = $retriever->search($request->string('question')->toString());
        $answer = $generator->generate($request->string('question')->toString(), $hits);

        return new QueryResource(['answer' => $answer, 'sources' => $hits]);
    }
}
