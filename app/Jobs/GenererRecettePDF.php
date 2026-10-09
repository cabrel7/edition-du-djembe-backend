<?php

namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenererRecettePDF implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $recetteData;
    public $pdfName;

    public function __construct(array $recetteData, string $pdfName)
    {
        $this->recetteData = $recetteData;
        $this->pdfName = $pdfName;
    }

    public function handle()
    {
        $pdf = Pdf::loadView('pdf.recette_design', $this->recetteData);
        $pdf->setPaper('a4');
        $pdf->setOptions(['isHtml5ParserEnabled' => true, 'isPhpEnabled' => true]);
        $pdf->save(public_path('./pdf/' . $this->pdfName));
    }
}
