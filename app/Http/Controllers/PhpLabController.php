<?php

namespace App\Http\Controllers;

use App\PhpLab\PhpLabRegistry;
use Illuminate\Contracts\View\View;

class PhpLabController extends Controller
{
    public function __construct(
        private readonly PhpLabRegistry $phpLab,
    ) {}

    public function index(): View
    {
        return view('php-lab.index', [
            'topics' => $this->phpLab->all(),
        ]);
    }

    public function show(string $topic): View
    {
        $topic = $this->phpLab->find($topic);

        abort_if($topic === null, 404, 'PHP Lab topic not found.');

        return view('php-lab.show', [
            'topic' => $topic,
            'topics' => $this->phpLab->all(),
        ]);
    }
}
