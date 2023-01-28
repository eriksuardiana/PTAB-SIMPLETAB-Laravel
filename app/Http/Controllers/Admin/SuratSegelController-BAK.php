<?php

namespace App\Http\Controllers\Admin;

use App\CtmPembayaran;
use App\Customer;
use App\Dapertement;
use App\Http\Controllers\Controller;
use App\Lock;
use App\LockAction;
use App\Staff;
use App\AreaStaff;
use App\Subdapertement;
use App\Traits\TraitModel;
use App\User;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use OneSignal;
use Yajra\DataTables\Facades\DataTables;
use PhpOffice\PhpWord\TemplateProcessor;
use DateTime;
use Illuminate\Support\Facades\App;
use Barryvdh\DomPDF\Facade\Pdf;
use App\CtmPelanggan;
use App\CtmWilayah;
use Illuminate\Support\Facades\Auth;

class SuratSegelController extends Controller
{
    public function suratPdf(Request $request)
    {
        $id = $request->id;
        $customer = Customer::join('map_koordinatpelanggan', 'map_koordinatpelanggan.nomorrekening', '=', 'tblpelanggan.nomorrekening')->where('tblpelanggan.nomorrekening', $id)
            ->first();
        $customer->year = date('Y');
        // dd($ctm);

        // ctm pay
        $date_now = date("Y-m-d");
        $date_comp = date("Y-m") . "-20";
        $month_next = date('n', strtotime('+1 month'));
        $month_now = ($month_next - 1);
        $month_now_new = date('n');
        $year_now = date("Y");
        $tunggakan = 0;
        $tagihan = 0;
        $denda = 0;
        $tindakan = ['tindakan' => ""];
        $inputStatus = ['inputStatus' => ""];
        $total = 0;
        $ctm_lock = 0;
        $last_4_month = date("Y-n-d", strtotime('-4 month', strtotime(date('Y-m-01'))));
        if ($date_now > $date_comp) {
            $ctm_lock_old = 0;
            $ctm = CtmPembayaran::selectRaw("tblpembayaran.*,tblpelanggan.*")
                ->join('tblpelanggan', 'tblpelanggan.nomorrekening', '=', 'tblpembayaran.nomorrekening')
                ->where('tblpembayaran.nomorrekening', $id)
                ->where('tblpelanggan.status', 1)
                ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                ->orderBy('tblpembayaran.bulanrekening', 'ASC')
                ->get();
        } else {
            $ctm_lock_old = 1;
            $ctm = CtmPembayaran::selectRaw("tblpembayaran.*,tblpelanggan.*")
                ->join('tblpelanggan', 'tblpelanggan.nomorrekening', '=', 'tblpembayaran.nomorrekening')
                ->where('tblpembayaran.nomorrekening', $id)
                ->where('tblpelanggan.status', 1)
                ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                ->orderBy('tblpembayaran.bulanrekening', 'ASC')
                ->get();
        }

        $status_paid_this_month = 0;
        foreach ($ctm as $key => $item) {
            //get this month paid
            if ($item->bulanrekening == $month_now_new && $item->tahunrekening == $year_now) {
                if ($item->statuslunas == 2) {
                    $status_paid_this_month = 1;
                }
            }
            $m3 = $item->bulanini - $item->bulanlalu;
            $sisa = $item->wajibdibayar - $item->sudahdibayar;
            $tagihan = $tagihan + $sisa;


            if ($month_now == $item->bulanrekening && $ctm_lock_old == 1) {
                $ctm_lock = 1;
            }

            if ($sisa > 0 && $ctm_lock == 0) {
                $tunggakan = $tunggakan + 1;
            }

            //if not paid
            if ($sisa > 0) {
                $item->tglbayarterakhir = "";
            }
            //set to prev
            $periode = date('Y-m', strtotime(date($item->tahunrekening . '-' . $item->bulanrekening . '-01') . " -1 month"));

            $dataPembayaran[$key] = [
                // 'no' => $key +1,
                'norekening' => $item->nomorrekening,
                'periode' => $periode,
                'tanggal' => $item->tglbayarterakhir,
                'm3' => $m3,
                'wajibdibayar' => $item->wajibdibayar,
                'sudahbayar' => $item->sudahdibayar,
                'denda' => $item->denda,
                'sisa' => $sisa,
            ];
        }

        if ($tunggakan > 0 && $tunggakan < 2) {
            $denda = 10000;
            $total = $tagihan + $denda;
            $denda = $denda;
        }
        if ($tunggakan > 1 && $tunggakan < 4) {
            $denda = 50000;
            $total = $tagihan + $denda;
            $denda = $denda;
        }
        if ($tunggakan > 3) {
            $denda = 'SSB (Sanksi Denda Setara Sambungan Baru)';
            $total = $tagihan;
        }

        if ($tunggakan === 2) {
            $tindakan = ['tindakan' => "notice"];
        } else if ($tunggakan === 3) {
            $cek = LockAction::where('customer_id', $id)->where('type', 'lock')->get();
            if (count($cek) >= 1) {
                $tindakan = ['tindakan' => "notice2"];
            } else {
                $tindakan = ['tindakan' => "lock"];
            }
        } else if ($tunggakan > 3) {
            $tindakan = ['tindakan' => "cabutan"];
        }

        $cekInput = LockAction::where('customer_id', $id)->where('type', $tindakan)->get();
        if (count($cekInput) >= 1) {
            $inputStatus = ["inputStatus" => "sudah"];
        } else {
            $inputStatus = ["inputStatus" => "belum"];
        }
        // foreach ($dataPembayaran as $value) {
        //     dd($value['speriode']);
        // }

        $recap = [
            'tagihan' => $tagihan,
            'denda' => $denda,
            'total' => $total,
            'tunggakan' => $tunggakan,
        ];

        // dd($customer);
        $day = date('D');
        $dayList = array(
            'Sun' => 'Minggu',
            'Mon' => 'Senin',
            'Tue' => 'Selasa',
            'Wed' => 'Rabu',
            'Thu' => 'Kamis',
            'Fri' => 'Jumat',
            'Sat' => 'Sabtu'
        );
        $day2Name = array(
            '01' => 'Satu',
            '02' => 'Dua',
            '03' => 'Tiga',
            '04' => 'Empat',
            '05' => 'Lima',
            '06' => 'Enam',
            '07' => 'Tujuh',
            '08' => 'Delapan',
            '09' => 'Sembilan',
            '10' => 'Sepuluh',
            '11' => 'Sebelas',
            '12' => 'Dua Belas',
            '13' => 'Tiga Belas',
            '14' => 'Empat Belas',
            '15' => 'Lima Belas',
            '16' => 'Enam Belas',
            '17' => 'Tujuh Belas',
            '18' => 'Delapan Belas',
            '19' => 'Sembilan Belas',
            '20' => 'Dua Puluh',
            '21' => 'Dua Puluh Satu',
            '22' => 'Dua Puluh Dua',
            '23' => 'Dua PUluh Tiga',
            '24' => 'Dua Puluh Empat',
            '25' => 'Dua Puluh Lima',
            '26' => 'Dua Puluh Enam',
            '27' => 'Dua Puluh tujuh',
            '28' => 'Dua Puluh Delapan',
            '29' => 'Dua Puluh Sembilan',
            '30' => 'Tiga Puluh',
            '31' => 'Tiga Puluh Satu',
        );
        $monthRomawi = array(
            0 => '',
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII'
        );
        $monthList = array(
            0 => '',
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juli',
            7 => 'Juni',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        );
        $yearRibu = array(
            0 => '',
            1 => 'Seribu',
            2 => 'Dua Ribu',
            3 => 'Tiga Ribu',
            4 => 'Empat Ribu',
            5 => 'Lima Ribu',
            6 => 'Enam Ribu',
            7 => 'Tujuh Ribu',
            8 => 'Delapan Ribu',
            9 => 'Sembilan Ribu',
        );
        $yearRatusan = array(
            0 => '',
            1 => 'Seratus',
            2 => 'Dua Ratus',
            3 => 'Tiga Ratus',
            4 => 'Empat Ratus',
            5 => 'Lima Ratus',
            6 => 'Enam Ratus',
            7 => 'Tujuh Ratus',
            8 => 'Delapan Ratus',
            9 => 'Sembilan Ratus',
        );

        if (date('y') == '12' || date('y') == '13' || date('y') == '14' || date('y') == '15' || date('y') == '16' || date('y') == '17' || date('y') == '18' || date('y') == '19') {
            $yearPuluh = array(
                0 => '',
                1 => 'sebelas',
                2 => 'Dua Belas',
                3 => 'Tiga Belas',
                4 => 'Empat Belas',
                5 => 'Lima Belas',
                6 => 'Enam Belas',
                7 => 'Tujuh Belas',
                8 => 'Delapan Belas',
                9 => 'Sembilan Belas',
            );
            $yearSatuan = array(
                0 => '',
                1 => '',
                2 => '',
                3 => '',
                4 => '',
                5 => '',
                6 => '',
                7 => '',
                8 => '',
                9 => '',
            );
        } else {
            $yearPuluh = array(
                0 => '',
                1 => 'sebelas',
                2 => 'Dua Puluh',
                3 => 'Tiga Puluh',
                4 => 'Empat Puluh',
                5 => 'Lima Puluh',
                6 => 'Enam Puluh',
                7 => 'Tujuh Puluh',
                8 => 'Delapan Puluh',
                9 => 'Sembilan Puluh',
            );
            $yearSatuan = array(
                0 => '',
                1 => 'satu',
                2 => 'Dua',
                3 => 'Tiga',
                4 => 'Empat',
                5 => 'Lima',
                6 => 'Enam',
                7 => 'Tujuh',
                8 => 'Delapan',
                9 => 'Sembilan',
            );
        }
        $angkaTertulis = "";
        $nominal = [
            0 => '',
            1 => '',
            2 => 'Puluh',
            3 => 'Ratus',
            4 => '',
            5 => 'Puluh',
            6 => 'Ratus',
            7 => 'Juta',
            8 => 'Puluh Juta',
            9 => 'Ratus Juta'
        ];
        $nominaldepan = array(
            0 => '',
            1 => 'Satu',
            2 => 'Dua',
            3 => 'Tiga',
            4 => 'Empat',
            5 => 'Lima',
            6 => 'Enam',
            7 => 'Tujuh',
            8 => 'Delapan',
            9 => 'Sembilan',
        );
        $jumlahT = array(
            0 => 'nol',
            1 => 'Satu',
            2 => 'Dua',
            3 => 'Tiga',
            4 => 'Empat',
            5 => 'Lima',
            6 => 'Enam',
            7 => 'Tujuh',
            8 => 'Delapan',
            9 => 'Sembilan',
        );
        // $total = "1111212";
        // dd(substr($total, 0, 1));
        for ($i = 0; $i <= strlen($total); $i++) {


            //diatas puluhan
            if (strlen($total) - $i > 5) {
                if (substr($total, $i, 1) == "1") {
                    if (strlen($total) - $i != 7) {
                        $angkaTertulis = $angkaTertulis . ' Se' . strtolower($nominal[strlen($total) - $i]);
                    } else {
                        $angkaTertulis = $angkaTertulis . ' ' . $nominaldepan[(int)substr($total, $i, 1)] . ' ' . $nominal[strlen($total) - $i];
                    }
                } else if (substr($total, $i, 1) != '0') {
                    $angkaTertulis = $angkaTertulis . ' ' . $nominaldepan[(int)substr($total, $i, 1)] . ' ' . $nominal[strlen($total) - $i];
                } else {
                }
            }

            //dibawah ratusan
            else {
                if (substr($total, $i, 1) == "0") {
                } else if (strlen($total) - $i === 5 && substr($total, $i, 1) == "1"  && substr($total, $i + 1, 1) != "1") {
                    $angkaTertulis = $angkaTertulis . ' ' . $nominaldepan[(int)substr($total, $i + 1, 1)] . ' belas';
                } else if (strlen($total) - $i === 5 && substr($total, $i, 1) == "1" && substr($total, $i + 1, 1) == "1") {
                    $angkaTertulis = $angkaTertulis . ' sebelas';
                } else if (strlen($total) - $i === 5 && substr($total, $i, 1) != "1") {
                    $angkaTertulis = $angkaTertulis . ' ' . $nominaldepan[(int)substr($total, $i, 1)] . ' ' . $nominal[strlen($total) - $i] . ' ' . $nominaldepan[(int)substr($total, $i + 1, 1)];
                } else if (strlen($total) - $i === 3) {
                    if (substr($total, $i, 1) == "1") {
                        if (strlen($total) - $i != 7) {
                            $angkaTertulis = $angkaTertulis . ' se' . $nominal[strlen($total) - $i];
                        } else {
                            $angkaTertulis = $angkaTertulis . ' ' . $nominaldepan[(int)substr($total, $i, 1)] . ' ' . $nominal[strlen($total) - $i];
                        }
                    } else if (substr($total, $i, 1) != '0') {
                        $angkaTertulis = $angkaTertulis . ' ' . $nominaldepan[(int)substr($total, $i, 1)] . ' ' . $nominal[strlen($total) - $i];
                    } else {
                    }
                } else if (strlen($total) - $i === 2 && substr($total, $i, 1) == "1"  && substr($total, $i + 1, 1) != "1") {
                    $angkaTertulis = $angkaTertulis . ' ' . $nominaldepan[(int)substr($total, $i + 1, 1)] . ' belas';
                } else if (strlen($total) - $i === 2 && substr($total, $i, 1) == "1" && substr($total, $i + 1, 1) == "1") {
                    $angkaTertulis = $angkaTertulis . ' sebelas';
                } else if (strlen($total) - $i === 2 && substr($total, $i, 1) != "1") {
                    $angkaTertulis = $angkaTertulis . ' ' . $nominaldepan[(int)substr($total, $i, 1)] . ' ' . $nominal[strlen($total) - $i] . ' ' . $nominaldepan[(int)substr($total, $i + 1, 1)];
                }

                // else if (strlen($total) - $i === 4 && substr($total, $i, 1) == "1") {
                //     $angkaTertulis = $angkaTertulis . ' ' . $nominaldepan[(int)substr($total, $i, 1)];
                // }
                if (strlen($total) - $i === 4) {
                    $angkaTertulis = $angkaTertulis . ' Ribu';
                } else {
                }
            }
        }
        // dd

        // dd($angkaTertulis);
        (date('d'));
        $staff = Staff::where('id', $request->staff_id)->first();
        $data = [
            'angkaTertulis' => $angkaTertulis,
            'day' => date('d'), 'month' => date('m'), 'year' => date('Y'),
            'monthRomawi' => $monthRomawi[date('n')],
            'day2Name' => $day2Name[date('d')], 'dayName' => $dayList[$day], 'monthName' => $monthList[date('n')],
            'yearRibu' => $yearRibu[substr(date('Y'), 0, 1)], 'yearRatus' => $yearRatusan[substr(date('Y'), 1, 1)],
            'yearPuluh' => $yearPuluh[substr(date('Y'), 2, 1)], 'yearSatuan' => $yearSatuan[substr(date('Y'), 3, 1)],
            'nama_staff' => $staff->name, 'dapartement' => 'Pelaksana Meter Segel', 'namapelanggan' => $customer->namapelanggan,
            'nomorrekening' => $customer->nomorrekening, 'address' => $customer->alamat, 'total' => rupiah($total),
            'idareal' => $customer->idareal, 'jumlahtunggakan' => $tunggakan, 'jumlahtunggakanT' => $jumlahT[$tunggakan]
        ];

        $firstBulan = $monthList[date('n', strtotime($dataPembayaran[0]['periode']))];
        $lastBulan = $monthList[date('n', strtotime($dataPembayaran[count($dataPembayaran) - 1]['periode']))];

        $dapertement =  ucwords(strtolower($request->dapertement));
        // dd($dapertement);
        if ($request->jenis == "penyegelan") {
            $pdf = pdf::loadView('admin.suratSegel.penyegelan', compact('data', 'lastBulan', 'firstBulan', 'dapertement'));
            $pdf->setPaper('Legal', 'potrait')->render();
            return $pdf->stream();
        } else if ($request->jenis == "pencabutan") {
            $pdf = pdf::loadView('admin.suratSegel.pencabutan', compact('data', 'lastBulan', 'firstBulan', 'dapertement'));
            $pdf->setPaper('Legal', 'potrait')->render();
            return $pdf->stream();
        } else if ($request->jenis == "perintahPenyegelan") {
            $pdf = pdf::loadView('admin.suratSegel.perintahPenyegelan', compact('data', 'lastBulan', 'firstBulan', 'dapertement'));
            $pdf->setPaper('Legal', 'potrait')->render();
            return $pdf->stream();
        } else if ($request->jenis == "perintahPencabutan") {
            $pdf = pdf::loadView('admin.suratSegel.perintahPencabutan', compact('data', 'lastBulan', 'firstBulan', 'dapertement'));
            $pdf->setPaper('Legal', 'potrait')->render();
            return $pdf->stream();
        } else if ($request->jenis == "hambatanPenyegelan") {
            $pdf = pdf::loadView('admin.suratSegel.hambatanPenyegelan', compact('data', 'lastBulan', 'firstBulan', 'dapertement'));
            $pdf->setPaper('Legal', 'potrait')->render();
            return $pdf->stream();
        } else if ($request->jenis == "hambatanPencabutan") {
            $pdf = pdf::loadView('admin.suratSegel.hambatanPencabutan', compact('data', 'lastBulan', 'firstBulan', 'dapertement'));
            $pdf->setPaper('Legal', 'potrait')->render();
            return $pdf->stream();
        } else {
        }
    }
    public function index(Request $request)
    {
        // dd(Auth::user());

        $date_now = date('Y-m-d');
        $date_comp = date('Y-m') . '-20';
        $last_4_month = date("Y-n-d", strtotime('-4 month', strtotime(date('Y-m-01'))));
        if (Auth::user()->dapertement_id != 0 && Auth::user()->subdapertement_id != 0 && Auth::user()->staff_id != 0) {
            if (Auth::user()->staff_id === 0) {
                $group_unit = Dapertement::select('dapertements.group_unit')
                    ->where('dapertements.id', Auth::user()->dapertement_id)->first()->group_unit;
                $data = CtmWilayah::select('id as code', 'NamaWilayah')->where('group_unit', $group_unit)->get();
                // dd($data[0]->code);
            } else {
                $data = AreaStaff::join('ptabroot_ctm.tblwilayah', 'tblwilayah.id', '=', 'area_id')->selectRaw('area_id as code,NamaWilayah')->where('staff_id', Auth::user()->staff_id)->get();
            }
            $areas = $data;
            // dd($data);
            $qrystf = Staff::selectRaw('staffs.*, subdapertements.name as subdapertements_name, area_staff.area_id, dapertements.name as dapertements_name ')
                ->join('dapertements', 'staffs.dapertement_id', '=', 'dapertements.id')
                ->join('subdapertements', 'subdapertements.id', '=', 'staffs.subdapertement_id')
                ->join('area_staff', 'staffs.id', '=', 'area_staff.staff_id')
                ->groupBy('staffs.id');
            for ($i = 0; $i < count($data); $i++) {
                if ($i < 1) {

                    $qrystf->where('area_id', $data[$i]->code)
                        ->where('subdapertement_id', 10)
                        ->orWhere('dapertements.group_unit', '>', 1)
                        ->where('area_id', $data[$i]->code);
                    // $data2 = $data2 . ' where idareal = ' . $data[$i]->area_id;
                } else {
                    $qrystf->orWhere('area_id', $data[$i]->code)
                        ->where('subdapertement_id', 10)
                        ->orWhere('dapertements.group_unit', '>', 1)
                        ->Where('area_id', $data[$i]->code);
                }
            }

            $qry = Customer::selectRaw('tblpelanggan.*, (((count(tblpembayaran.statuslunas) * 2) - sum(tblpembayaran.statuslunas)) DIV 2) as jumlahtunggakan,  (case when( (((count(tblpembayaran.statuslunas) * 2) - sum(tblpembayaran.statuslunas)) DIV 2) > 1 ) THEN 1 ELSE 0 END) as statusnunggak')
                ->join('tblpembayaran', 'tblpelanggan.nomorrekening', '=', 'tblpembayaran.nomorrekening');

            // isi pertama
            if ($date_now > $date_comp) {
                if ($request->staff != '') {
                    // $data = AreaStaff::select('area_id')->where('staff_id', $request->staff)->get();
                    if (count($data) > 0) {

                        for ($i = 0; $i < count($data); $i++) {
                            if ($i < 1) {

                                $qry->where('tblpelanggan.idareal', $data[$i]->code)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                                // $data2 = $data2 . ' where idareal = ' . $data[$i]->code;
                            } else {
                                $qry->orWhere('tblpelanggan.idareal', $data[$i]->code)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                            }
                        }
                    } else {
                        $qry->where('tblpelanggan.nomorrekening', null);
                    }

                    $qry->FilterWilayah(request()->input('area'))->groupBy('tblpembayaran.nomorrekening');
                } else {
                    if (count($data) > 0) {

                        for ($i = 0; $i < count($data); $i++) {
                            if ($i < 1) {

                                $qry->where('tblpelanggan.idareal', $data[$i]->code)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                                // $data2 = $data2 . ' where idareal = ' . $data[$i]->area_id;
                            } else {
                                $qry->orWhere('tblpelanggan.idareal', $data[$i]->code)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                            }
                        }
                    } else {
                        $qry->where('tblpelanggan.nomorrekening', null);
                    }

                    $qry->FilterWilayah(request()->input('area'))->groupBy('tblpembayaran.nomorrekening');
                }
            } else {


                if ($request->staff != '') {
                    $data = AreaStaff::select('area_id')->where('staff_id', $request->staff)->get();
                    if (count($data) > 0) {

                        for ($i = 0; $i < count($data); $i++) {
                            if ($i < 1) {

                                $qry->where('tblpelanggan.idareal', $data[$i]->area_id)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                                // $data2 = $data2 . ' where idareal = ' . $data[$i]->area_id;
                            } else {
                                $qry->orWhere('tblpelanggan.idareal', $data[$i]->area_id)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                            }
                        }
                    } else {
                        $qry->where('tblpelanggan.nomorrekening', null);
                    }

                    $qry->FilterWilayah(request()->input('area'))->groupBy('tblpembayaran.nomorrekening');
                } else {
                    if (count($data) > 0) {

                        for ($i = 0; $i < count($data); $i++) {
                            if ($i < 1) {

                                $qry->where('tblpelanggan.idareal', $data[$i]->code)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                                // $data2 = $data2 . ' where idareal = ' . $data[$i]->area_id;
                            } else {
                                $qry->orWhere('tblpelanggan.idareal', $data[$i]->code)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                            }
                        }
                    } else {
                        $qry->where('tblpelanggan.nomorrekening', null);
                    }

                    $qry->FilterWilayah(request()->input('area'))->groupBy('tblpembayaran.nomorrekening');
                }
            }
        }

        // digunakan untuk admin
        else if (Auth::user()->name == 'ADMIN') {
            $areas = CtmWilayah::select('id as code', 'NamaWilayah')->get();
            $qrystf = Staff::selectRaw('staffs.*, subdapertements.name as subdapertements_name, area_staff.area_id, dapertements.name as dapertements_name ')
                ->join('dapertements', 'staffs.dapertement_id', '=', 'dapertements.id')
                ->join('subdapertements', 'subdapertements.id', '=', 'staffs.subdapertement_id')
                ->join('area_staff', 'staffs.id', '=', 'area_staff.staff_id')
                ->where('subdapertement_id', 10)
                ->orWhere('dapertements.group_unit', '>', 1)
                ->groupBy('staffs.id');
            // dd($qrystf->get());
            $qry = Customer::selectRaw('tblpelanggan.*, (((count(tblpembayaran.statuslunas) * 2) - sum(tblpembayaran.statuslunas)) DIV 2) as jumlahtunggakan,  (case when( (((count(tblpembayaran.statuslunas) * 2) - sum(tblpembayaran.statuslunas)) DIV 2) > 1 ) THEN 1 ELSE 0 END) as statusnunggak')
                ->join('tblpembayaran', 'tblpelanggan.nomorrekening', '=', 'tblpembayaran.nomorrekening');
            if ($date_now > $date_comp) {
                if ($request->staff != '') {
                    $data = AreaStaff::select('area_id')->where('staff_id', $request->staff)->get();
                    if (count($data) > 0) {

                        for ($i = 0; $i < count($data); $i++) {
                            if ($i < 1) {

                                $qry->where('tblpelanggan.idareal', $data[$i]->area_id)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                                // $data2 = $data2 . ' where idareal = ' . $data[$i]->area_id;
                            } else {
                                $qry->orWhere('tblpelanggan.idareal', $data[$i]->area_id)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                            }
                        }
                    } else {
                        $qry->where('tblpelanggan.nomorrekening', null);
                    }

                    $qry->FilterWilayah(request()->input('area'))->groupBy('tblpembayaran.nomorrekening');
                } else {
                    $qry->having('jumlahtunggakan', '>', 1)
                        ->where('tblpelanggan.status', 1)
                        ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                        ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                        ->FilterWilayah(request()->input('area'))
                        ->groupBy('tblpembayaran.nomorrekening')
                        ->FilterStatus(request()->input('status'));
                }
            } else {


                if ($request->staff != '') {
                    $data = AreaStaff::select('area_id')->where('staff_id', $request->staff)->get();
                    if (count($data) > 0) {

                        for ($i = 0; $i < count($data); $i++) {
                            if ($i < 1) {

                                $qry->where('tblpelanggan.idareal', $data[$i]->area_id)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                                // $data2 = $data2 . ' where idareal = ' . $data[$i]->area_id;
                            } else {
                                $qry->orWhere('tblpelanggan.idareal', $data[$i]->area_id)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                            }
                        }
                    } else {
                        $qry->where('tblpelanggan.nomorrekening', null);
                    }

                    $qry->FilterWilayah(request()->input('area'))->groupBy('tblpembayaran.nomorrekening');
                } else {
                    $qry->having('jumlahtunggakan', '>', 1)
                        ->where('tblpelanggan.status', 1)
                        ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                        ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                        ->FilterWilayah(request()->input('area'))
                        ->groupBy('tblpembayaran.nomorrekening')
                        ->FilterStatus(request()->input('status'));
                }
            }
        }

        // digunakan untuk admin
        else {
            $areas = CtmWilayah::select('id as code', 'NamaWilayah')->get();
            $qrystf = Staff::selectRaw('staffs.*, subdapertements.name as subdapertements_name, area_staff.area_id, dapertements.name as dapertements_name ')
                ->join('dapertements', 'staffs.dapertement_id', '=', 'dapertements.id')
                ->join('subdapertements', 'subdapertements.id', '=', 'staffs.subdapertement_id')
                ->join('area_staff', 'staffs.id', '=', 'area_staff.staff_id')
                ->where('subdapertement_id', 10)
                ->orWhere('dapertements.group_unit', '>', 1)
                ->groupBy('staffs.id');
            // dd($qrystf->get());
            $qry = Customer::selectRaw('tblpelanggan.*, (((count(tblpembayaran.statuslunas) * 2) - sum(tblpembayaran.statuslunas)) DIV 2) as jumlahtunggakan,  (case when( (((count(tblpembayaran.statuslunas) * 2) - sum(tblpembayaran.statuslunas)) DIV 2) > 1 ) THEN 1 ELSE 0 END) as statusnunggak')
                ->join('tblpembayaran', 'tblpelanggan.nomorrekening', '=', 'tblpembayaran.nomorrekening');
            if ($date_now > $date_comp) {
                if ($request->staff != '') {
                    $data = AreaStaff::select('area_id')->where('staff_id', $request->staff)->get();
                    if (count($data) > 0) {

                        for ($i = 0; $i < count($data); $i++) {
                            if ($i < 1) {

                                $qry->where('tblpelanggan.idareal', $data[$i]->area_id)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                                // $data2 = $data2 . ' where idareal = ' . $data[$i]->area_id;
                            } else {
                                $qry->orWhere('tblpelanggan.idareal', $data[$i]->area_id)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                            }
                        }
                    } else {
                        $qry->where('tblpelanggan.nomorrekening', null);
                    }

                    $qry->FilterWilayah(request()->input('area'))->groupBy('tblpembayaran.nomorrekening');
                } else {
                    $qry->having('jumlahtunggakan', '>', 1)
                        ->where('tblpelanggan.status', 1)
                        ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<=', date('Y-n-01'))
                        ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                        ->FilterWilayah(request()->input('area'))
                        ->groupBy('tblpembayaran.nomorrekening')
                        ->FilterStatus(request()->input('status'));
                }
            } else {


                if ($request->staff != '') {
                    $data = AreaStaff::select('area_id')->where('staff_id', $request->staff)->get();
                    if (count($data) > 0) {

                        for ($i = 0; $i < count($data); $i++) {
                            if ($i < 1) {

                                $qry->where('tblpelanggan.idareal', $data[$i]->area_id)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                                // $data2 = $data2 . ' where idareal = ' . $data[$i]->area_id;
                            } else {
                                $qry->orWhere('tblpelanggan.idareal', $data[$i]->area_id)
                                    ->where('tblpelanggan.status', 1)
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                                    ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                                    ->having('jumlahtunggakan', '>', 1)
                                    ->FilterStatus(request()->input('status'));
                            }
                        }
                    } else {
                        $qry->where('tblpelanggan.nomorrekening', null);
                    }

                    $qry->FilterWilayah(request()->input('area'))->groupBy('tblpembayaran.nomorrekening');
                } else {
                    $qry->having('jumlahtunggakan', '>', 1)
                        ->where('tblpelanggan.status', 1)
                        ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '<', date('Y-n-01'))
                        ->whereDate(DB::raw('concat(tblpembayaran.tahunrekening,"-",tblpembayaran.bulanrekening,"-01")'), '>=', $last_4_month)
                        ->FilterWilayah(request()->input('area'))
                        ->groupBy('tblpembayaran.nomorrekening')
                        ->FilterStatus(request()->input('status'));
                }
            }
        }

        // dd($qry->get());
        if ($request->ajax()) {

            $table = Datatables::of($qry);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $createGate = 'create';
                $crudRoutePart = 'suratsegel';
                // $lockGate = $row->statusnunggak;
                return view('partials.datatablesActionsPdf', compact(
                    'createGate',
                    'crudRoutePart',
                    'row'
                ));
            });
            $table->editColumn('nomorrekening', function ($row) {
                return $row->nomorrekening ? $row->nomorrekening : "";
            });

            $table->editColumn('namapelanggan', function ($row) {
                return $row->namapelanggan ? $row->namapelanggan : "";
            });

            $table->editColumn('alamat', function ($row) {
                return $row->alamat ? $row->alamat : "";
            });
            $table->editColumn('idareal', function ($row) {
                return $row->idareal ? $row->idareal : "";
            });

            $table->editColumn('jumlahtunggakan', function ($row) {
                return $row->jumlahtunggakan ? $row->jumlahtunggakan : 0;
            });

            $table->editColumn('statusnunggak', function ($row) {
                if ($row->jumlahtunggakan == 0) {
                    return '<span class="badge bg-success">Lunas</span>';
                } else if ($row->jumlahtunggakan == 1) {
                    return '<span class="badge bg-warning">Awas</span>';
                } else {
                    return '<span class="badge bg-danger">Tunggak</span>';
                }
            });

            $table->rawColumns(['actions', 'placeholder', 'statusnunggak']);

            $table->addIndexColumn();
            return $table->make(true);
        }
        $staff = $qrystf->get();
        return view('admin.suratSegel.suratSegel', compact('staff', 'areas'));
    }

    public function create($id)
    {
        // abort_unless(\Gate::allows('user_create'), 403);
        $id = $id;
        $customer = Customer::where('nomorrekening', $id)->first();
        $dapertement = Dapertement::where('group_unit', '>', 1)->orWhere('id', 2)->get();
        // $staff = Staff::selectRaw('staffs.id as id, staffs.name as name')->join('area_staff', 'staffs.id', '=', 'area_staff.staff_id')->where('subdapertement_id', 10)->where('area_id', $customer->idareal)->groupBy('staffs.id')->get();
        $staff = User::selectRaw('staffs.*, subdapertements.name as subdapertements_name, area_staff.area_id, dapertements.name as dapertements_name ')
            ->join('subdapertements', 'subdapertements.id', '=', 'subdapertement_id')
            ->join('staffs', 'staffs.id', '=', 'users.staff_id')->join('dapertements', 'dapertements.id', '=', 'users.dapertement_id')
            ->rightJoin('area_staff', 'area_staff.staff_id', '=', 'staffs.id')
            ->where('users.subdapertement_id', 10)
            ->where('area_staff.area_id', $customer->idareal)
            ->orWhere('dapertements.group_unit', '>', 1)
            ->where('area_staff.area_id', $customer->idareal)
            ->orderBy('users.subdapertement_id', 'ASC')
            ->get();
        // dd($staff);
        return view('admin.suratSegel.create', compact('staff', 'id', 'customer', 'dapertement'));
    }
}
