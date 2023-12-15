<?php

namespace App\Http\Livewire\Proses;

use App\Models\Alternatif;
use App\Models\Kriteria;
use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;

class Index extends Component
{
	public function render()
	{
		$alternatifs = $this->proses();
		return view('livewire.proses.index', compact('alternatifs'));
	}

	public function print()
	{
		// abaikan garis error di bawah 'Pdf' jika ada.
		$pdf = Pdf::loadView('laporan.cetak', ['data' => $this->proses()])->output();
		// return $pdf->download('Laporan.pdf');
		return response()->streamDownload(fn () => print($pdf), 'Laporan.pdf');
	}

	// proses metode PSI
	public function proses()
	{
		$alternatifs = Alternatif::orderBy('kode')->get();
		$kriterias = Kriteria::orderBy('kode')->get('type')->toArray();
		// dd($kriterias);

		
		// Langkah 1: Membentuk matriks keputusan dari nilai kriteria untuk setiap alternatif
$Xij = [];
foreach ($alternatifs as $ka => $alt) {
    foreach ($alt->kriteria as $kk => $krit) {
        $Xij[$ka][$kk] = $krit->pivot->nilai;
    }
}

$rows = count($Xij);
$cols = count($Xij[0]);

// Langkah 2: Normalisasi matriks keputusan
$Nij = [];
for ($j = 0; $j < $cols; $j++) {
    $xj = [];
    for ($i = 0; $i < $rows; $i++) {
        $xj[] = $Xij[$i][$j];
    }

    $divisor = max($xj);
    $cost = false;
    if ($kriterias[$j]['type'] == false) {
        $cost = true;
        $divisor = min($xj);
    }

    foreach ($xj as $kj => $x) {
        $Nij[$kj][$j] = $cost ? ($divisor / $x) : ($x / $divisor);
    }
}

// Langkah 3: Menghitung nilai rata-rata dari masing-masing kolom
$EN = array_map('array_sum', $Nij);
$N = array_map(function ($e) use ($rows) {
    return $e / $rows;
}, $EN);

// Langkah 4: Menghitung variasi preferensi
$Tj = [];
for ($i = 0; $i < $cols; $i++) {
    for ($j = 0; $j < $rows; $j++) {
        $Tj[$i][$j] = pow($Nij[$j][$i] - $N[$i], 2);
    }
}

// Langkah 5: Menghitung total variasi preferensi tiap kriteria
$TTj = array_map('array_sum', $Tj);

// Langkah 6: Menentukan penyimpangan nilai preferensi
$Omega = array_map(fn ($ttj) => 1 - $ttj, $TTj);
$EOmega = array_sum($Omega);

// Langkah 7: Menghitung bobot kriteria
$Wj = array_map(fn ($o) => $o / $EOmega, $Omega);

// Langkah 8: Menghitung PSI untuk setiap alternatif
$ThetaI = [];
for ($i = 0; $i < $cols; $i++) {
    for ($j = 0; $j < $rows; $j++) {
        $ThetaI[$j][$i] = $Nij[$j][$i] * $Wj[$i];
    }
}

// Langkah 9: Penjumlahan hasil PSI untuk setiap alternatif
$TThetaI = array_map('array_sum', $ThetaI);

// Langkah 10: Menetapkan nilai PSI pada setiap alternatif
foreach ($alternatifs as $key => $alternatif) {
    $alternatif->nilai = round($TThetaI[$key], 4);
}

return $alternatifs;

	}
}