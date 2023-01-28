<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SIMPELTAB</title>
    <link href="{{ asset('css/printsubhumas.css') }}" rel="stylesheet" />

    <style type="text/css">
        .baris1 {
            display: flex;
            margin-top: 20px;
        }
        .kiri {
            margin-left: 30px;
            width: 25%;
            /* background-color: rgb(5, 0, 69); */
            align-items: left;
            /* text-align: center; */
        }
        .kanan {
            margin-right: 30px;
            width: 25%;
            /* background-color: rgb(69, 18, 0); */
            margin-left: auto;
            /* text-align: center; */
        }
        .tengah {
            width: 25%;
            /* background-color: rgb(69, 18, 0); */
            margin: auto;
            /* text-align: center; */
        }
    
    </style>
    
    
        <style type="text/css" media="print">
        .baris1 {
            margin-top: 20px;
            display: flex;
        }
        .kiri {
            width: 25%;
            /* background-color: rgb(5, 0, 69); */
            align-items: left;
            /* text-align: center; */
       
        }
        .kanan {
            width: 25%;
            /* background-color: rgb(69, 18, 0); */
            margin-left: auto;
            /* text-align: center; */
        }
        .tengah {
            width: 25%;
            /* background-color: rgb(69, 18, 0); */
            margin: auto;
            /* text-align: center; */
        }
        @media print {
        @page {
            /* margin-top: 0; */
            margin-bottom: 0;
        }
        body {
            /* padding-top: 72px; */
            padding-bottom: 72px ;
        }
    }
            </style>

</head>
<body class="A4"  onload="onload()" >

    {{-- {{ dd($tickets) }} --}}
    <section class="sheet padding-10mm">
        <h3>REKAPITULASI PERMINTAAN SERVICE</h3>
        <!-- <h3>BULAN : {{ count($tickets) > 0 ?  date('F Y', strtotime($tickets[0]->created_at)) : 'Tidak ada data kosong' }} </h3> -->
        <h3>PERIODE : Dari {{$request->from}} Sampai {{$request->to}}</h3>
        <table class="table">
        <tr>
            <th rowspan="3">No</th>
            <th rowspan="3">HARI</th>
            <th rowspan="3">TANGGAL</th>
            <th rowspan="3">AREA</th>
            <th rowspan="3">No.SBG</th>
            <th rowspan="3">NAMA</th>
            <th rowspan="3">ALAMAT</th>
            <th colspan="3">KELUHAN MASUK</th>
            <th colspan="2">RENCANA PENANGANAN SERVICE</th>
            <th rowspan="3">T/P/R/L</th>
            <th rowspan="3">&nbsp;</th>
            <th colspan="2">TINDAKAN PENYELESESAIAN</th>
            <th rowspan="3">KETERANGAN</th>
        </tr>
        <tr>
            <th colspan="3" class="text-center">Jam</th>
            <th rowspan="2" class="text-center">KODE</th>
            <th rowspan="2" class="text-center">KELUHAN</th>
            <th rowspan="2" class="text-center">TANGGAL</th>
            <th rowspan="2" class="text-center">KECEPATAN (HARI)</th>
        </tr>
        <tr>
            <th>AWAL</th>
            <th>AKHIR</th>
            <th>WAKTU</th>
        </tr>
        {{-- isi data --}}
        <?php $no=1 ?>
        @foreach ($tickets as $ticket)
    
            <tr>
                <td class="text-center">{{$no++}}</td>
                <td>{{Hari_ini(date('l', strtotime($ticket->created_at)))}}</td>
                <td>{{$ticket->created_at->format('d/m/Y')}}</td>
                <td>{{$ticket->area }}</td>
                <td>{{$ticket->customer->id}}</td>
                <td>{{ $ticket->customer->name }}</td>
                <td>{{$ticket->customer->address}}</td>
                <td>@if ($ticket->created_at != null) {{$ticket->created_at->format('H:i:s')}} @endif</td>
                <td>@if ($ticket->delegated_at != null) {{$ticket->delegated_at->format('H:i:s')}} @else {{$ticket->created_at->format('H:i:s')}} @endif</td>
                <?php 
                    $mulai  = date_create($ticket->created_at);
                    $selesai = $ticket->delegated_at != null ? date_create($ticket->delegated_at) : date_create($ticket->created_at);
                    $hasil  = date_diff( $mulai, $selesai );
                ?>
                <td>{{$hasil->format('%H:%I:%S')}}</td>
                <td>{{$ticket->category->code}}</td>
                <td>{{$ticket->category->name}}</td>
                <td>        
                    <?php $type = '';
                    if($ticket->category->categorytype->name == 'Teknik'){
                        echo 'T';
                    }elseif($ticket->category->categorytype->name == 'Pelayanan'){
                        echo  'P';
                    }elseif($ticket->category->categorytype->name == 'Rekening'){
                        echo  'R';
                    }else{
                        echo  'L';
                    }
                ?>
                </td>
                <td>0</td>
                <td>{{$ticket->updated_at->format('d/m/Y')}}</td>
                   <?php 
                    $datetime1 = strtotime($ticket->created_at);
                    $datetime2 = strtotime($ticket->updated_at);
                 
                    $days = (int)(($datetime2 - $datetime1)/86400); ?>
                <td>{{$days}}</td>
                <?php $number=1;?>
                <td> 
                    @foreach ($ticket->action as $ticketaction)
                    {{$number++ . '. ' . $ticketaction->memo}}<br>
                    @endforeach
                </td>
            </tr>
          
        @endforeach
        {{-- batas isi data  --}}
    </table>
    </section>

    <div class="baris1">
        <div class="kiri">
            <div class="" style="text-align : center">Mengetahui</div>
            <div class="jabatan" style="margin-bottom : 80px; text-align : center">Ka.{{ $mengetahui }}</div>
    <div class="nama"></div>
    <div class="nip" style = "border-top-style: solid; "></div>
        </div>
    
        <div class="kanan">
            <div class="" style="text-align : center">Tabanan, {{ date('d') }} {{ $month }} {{ date('Y') }}</div>
            <div class="" style="text-align : center">Di buat oleh</div>
            <div class="jabatan" style="margin-bottom : 80px; text-align : center">Ka.{{ $dibuat }}</div>
            <div class="nama" style = ""></div>
    <div class="nama" style = "border-bottom-style: solid; "></div>
        </div>
    </div>
    <div class="tengah">
        <div class="" style="text-align : center">Menyetujui</div>
        <div class="jabatan" style="margin-bottom : 80px; text-align : center">{{ $menyetujui }}</div>
    <div class="nama" style="text-align : center;">{{ $director_name }}</div>
    <div class="nip" style = "border-top-style: solid; "></div>
    </div>
    <script>
    onload = function (){
        window.print();
    }
</script>
</body>
</html>