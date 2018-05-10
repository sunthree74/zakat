<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Http\Request;
use App\JenisZakat;
use App\Transaksi;
use App\Muzakki;
use Response;

class ZakatController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('zakat-list');
    }

    public function create($id = null)
    {
        $jenis_zakats = JenisZakat::all();
        if ($id == null) {
           return view('bayar-zakat', compact('jenis_zakats'));
        } else{
            $idmuzakki = \base64_decode($id);
            $muzakki = Muzakki::findOrfail($idmuzakki);
            return view('bayar-zakat', compact('jenis_zakats','muzakki'));
        }
    	
    }

    public function cariMuzakki(Request $request, $nama)
    {
        $queries = Muzakki::where('name', 'LIKE', '%'.$nama.'%')
            ->take(5)->get();
            $html = array();
        foreach($queries as $q){
            $url = url('/pelaksanaan-zakat/'.base64_encode($q->id).'');
            $html[] = "<tr>"
                ."<td>".$q->name."</td>"
                ."<td>".$q->alamat."</td>"
                ."<td>".$q->nohp."</td>"
                ."<td><a class'btn bg-indigo waves-effect' href='".$url."'><i class='material-icons'>launch</i></a></td>"
                ."</tr>";
        }
        
        return Response($html);
    }

    public function getNominal(Request $request, $nominal)
    {
        $hasil = JenisZakat::findOrfail($nominal);
        return Response::json($hasil);
    }

    public function storeZakat(Request $request)
    {
        if ($request->cek == "new") {
            $Muzakki = Muzakki::create([
                'name' => $request->nama,
                'email' => $request->email,
                'nohp' => $request->noHP,
                'alamat' => $request->alamat,
                'jeniskelamin' => $request->kelamin,
            ]);

            $trans = Transaksi::create([
                'muzakki_id' => $Muzakki->id,
                'user_id' => Auth::user()->id,
                'jeniszakat_id' => $request->tipe,
                'jiwa' => $request->jiwa,
                'beras_fitrah' => $request->beras,
                'uang_fitrah' => $request->uang,
                'fidyah' => $request->fidyah,
                'zakat_maal' => $request->maal,
                'infaq' => $request->infaq,
            ]);
        } elseif($request->cek == "old") {
            $idmuzakki = base64_decode($request->idm);
            $Muzakki = Muzakki::findOrfail($idmuzakki);
            $Muzakki->name = $request->nama;
            $Muzakki->email = $request->email;
            $Muzakki->nohp = $request->noHP;
            $Muzakki->alamat = $request->alamat;
            $Muzakki->jeniskelamin = $request->kelamin;
            $Muzakki->save();

            $trans = Transaksi::create([
                'muzakki_id' => $idmuzakki,
                'user_id' => Auth::user()->id,
                'jeniszakat_id' => $request->tipe,
                'jiwa' => $request->jiwa,
                'beras_fitrah' => $request->beras,
                'uang_fitrah' => $request->uang,
                'fidyah' => $request->fidyah,
                'zakat_maal' => $request->maal,
                'infaq' => $request->infaq,
            ]);
        }
        $idtrans = base64_encode($trans->id);
        return redirect('konfirmasi/'.$idtrans);
    }

    public function showInsertedZakat(Request $resuest, $id)
    {
        $idTransaksi = base64_decode($id);
        $transaksi = Transaksi::findOrfail($idTransaksi);

        return view('konfirmasi-zakat', compact('transaksi'));
    }

    public function getZakatData()
    {
        $zakats = Transaksi::select(['transaksis.id','muzakkis.name as nama', 'transaksis.jiwa', 'jenis_zakats.jenis', 'transaksis.beras_fitrah', 'transaksis.uang_fitrah', 'transaksis.fidyah', 'transaksis.zakat_maal', 'transaksis.infaq', 'users.name'])
            ->join('muzakkis', 'transaksis.muzakki_id', '=', 'muzakkis.id')
            ->join('users', 'transaksis.user_id', '=', 'users.id')
            ->join('jenis_zakats', 'transaksis.jeniszakat_id', '=', 'jenis_zakats.id')
            ->orderBy('transaksis.id');
        
        // $zakats = DB::table('transaksis')->join('muzakkis', 'transaksis.muzakki_id', '=', 'muzakkis.id')
        //     ->join('users', 'transaksis.user_id', '=', 'users.id')
        //     ->join('jenis_zakats', 'transaksis.jeniszakat_id', '=', 'jenis_zakats.id')
        //     ->select(['transaksis.id','muzakkis.name as nama', 'transaksis.jiwa', 'jenis_zakats.jenis', 'transaksis.beras_fitrah', 'transaksis.uang_fitrah', 'transaksis.fidyah', 'transaksis.zakat_maal', 'transaksis.infaq', 'users.name'])
        //     ->where('transaksis.deleted_at', '=', NULL);

        return Datatables::of($zakats)
            ->addColumn('action', function ($zakats) {
                return '<form method="POST" id="myform" action="'.route('zakat.destroy',base64_encode($zakats->id)).'">'
                .'<input type="hidden" name="_method" value="DELETE">'
                .'<button title="Hapus Data" type="button" class="btn btn-xs btn-primary waves-effect" id="hapus"><i class="material-icons">delete</i></button>'
                .'</form>'
                .'<a title="Rubah Data" class="btn btn-xs btn-primary" href="'. url('edit-transaksi')."/".base64_encode($zakats->id) .'"><i class="material-icons">border_color</i></a>' 
                .'<a title="Print Kwitansi" href="'. url('make-invoice').'/'.base64_encode($zakats->id).'" class="btn btn-xs btn-primary" target="_blank"><i class="material-icons">print</i></a>';
            })
            ->addColumn('uang_zakat', function ($zakats) {
                return 'Rp. '.number_format($zakats->uang_fitrah,0,'',',');
            })
            ->addColumn('fidyah', function ($zakats) {
                return 'Rp. '.number_format($zakats->fidyah,0,'',',');
            })
            ->addColumn('maal', function ($zakats) {
                return 'Rp. '.number_format($zakats->zakat_maal,0,'',',');
            })
            ->addColumn('infaq', function ($zakats) {
                return 'Rp. '.number_format($zakats->infaq,0,'',',');
            })
            ->editColumn('id', 'ID: {{$id}}')
            ->removeColumn('password')
            ->make(true);
    }

    public function editZakat(Request $resuest, $id){
        $idTransaksi = base64_decode($id);
        $jenis_zakats = JenisZakat::all();
        $transaksi = Transaksi::findOrfail($idTransaksi);

        return view('edit-zakat', compact('transaksi','jenis_zakats'));
    }

    public function updateZakat(Transaksi $transaksi, Request $request)
    {
        $transaksi->update([
            'jeniszakat_id' => request('tipe'),
            'jiwa' => request('jiwa'),
            'beras_fitrah' => request('beras'),
            'uang_fitrah' => request('uang'),
            'fidyah' =>request('fidyah'),
            'zakat_maal' => request('maal'),
            'infaq' => request('infaq'),
        ]);
        
        $Muzakki = Muzakki::findOrfail(base64_decode(request('_idm')));
        $Muzakki->name = request('nama');
        $Muzakki->email = request('email');
        $Muzakki->nohp = request('noHP');
        $Muzakki->alamat = request('alamat');
        $Muzakki->jeniskelamin = request('kelamin');
        $Muzakki->save();

        return redirect()->route('zakat.confirmation',base64_encode($transaksi->id));
    }

    public  function destroy($id)
    {
        $idzakat = base64_decode($id);
        $zakat = Transaksi::findOrfail($idzakat);
        $zakat->delete();

        return redirect()->route('zakat')->withDanger('Data Transaksi Zakat Milik '.$zakat->muzakki->name.' Berhasil Dihapus');
    }

    public function createPDF($id)
    {
        $id_transaksi = base64_decode($id);
        $transaksi = Transaksi::findOrfail($id_transaksi);
        $val = array($transaksi->uang_fitrah,$transaksi->fidyah,$transaksi->zakat_maal,$transaksi->infaq);
        $data = array_sum($val);

        return view('zakat.invoice',compact('transaksi','data'));
    }

    public static function convertBilanganToKalimat($bilangan)
    {
        $angka = array('0','0','0','0','0','0','0','0','0','0',
                 '0','0','0','0','0','0');
        $kata = array('','satu','dua','tiga','empat','lima',
                        'enam','tujuh','delapan','sembilan');
        $tingkat = array('','ribu','juta','milyar','triliun');

        $panjang_bilangan = strlen($bilangan);

        /* pengujian panjang bilangan */
        if ($panjang_bilangan > 15) {
            $kalimat = "Diluar Batas";
            return $kalimat;
        }

        /* mengambil angka-angka yang ada dalam bilangan,
            dimasukkan ke dalam array */
        for ($i = 1; $i <= $panjang_bilangan; $i++) {
            $angka[$i] = substr($bilangan,-($i),1);
        }

        $i = 1;
        $j = 0;
        $kalimat = "";


        /* mulai proses iterasi terhadap array angka */
        while ($i <= $panjang_bilangan) {

            $subkalimat = "";
            $kata1 = "";
            $kata2 = "";
            $kata3 = "";

            /* untuk ratusan */
            if ($angka[$i+2] != "0") {
            if ($angka[$i+2] == "1") {
                $kata1 = "seratus";
            } else {
                $kata1 = $kata[$angka[$i+2]] . " ratus";
            }
            }

            /* untuk puluhan atau belasan */
            if ($angka[$i+1] != "0") {
            if ($angka[$i+1] == "1") {
                if ($angka[$i] == "0") {
                $kata2 = "sepuluh";
                } elseif ($angka[$i] == "1") {
                $kata2 = "sebelas";
                } else {
                $kata2 = $kata[$angka[$i]] . " belas";
                }
            } else {
                $kata2 = $kata[$angka[$i+1]] . " puluh";
            }
            }

            /* untuk satuan */
            if ($angka[$i] != "0") {
            if ($angka[$i+1] != "1") {
                $kata3 = $kata[$angka[$i]];
            }
            }

            /* pengujian angka apakah tidak nol semua,
            lalu ditambahkan tingkat */
            if (($angka[$i] != "0") OR ($angka[$i+1] != "0") OR
                ($angka[$i+2] != "0")) {
            $subkalimat = "$kata1 $kata2 $kata3 " . $tingkat[$j] . " ";
            }

            /* gabungkan variabe sub kalimat (untuk satu blok 3 angka)
            ke variabel kalimat */
            $kalimat = $subkalimat . $kalimat;
            $i = $i + 3;
            $j = $j + 1;

        }

        /* mengganti satu ribu jadi seribu jika diperlukan */
        if (($angka[5] == "0") AND ($angka[6] == "0")) {
            $kalimat = str_replace("satu ribu","seribu",$kalimat);
        }

        return trim($kalimat);
    }

    public static function tanggalIndo($tanggal, $cetak_hari = false)
    {
        $hari = array ( 1 =>    'Senin',
            'Selasa',
            'Rabu',
            'Kamis',
            'Jumat',
            'Sabtu',
            'Minggu'
            );
            
        $bulan = array (1 =>   'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
            );
        $split    = explode('-', $tanggal);
        $tgl_indo = $split[2] . ' ' . $bulan[ (int)$split[1] ] . ' ' . $split[0];
        
        if ($cetak_hari) {
        $num = date('N', strtotime($tanggal));
        return $hari[$num] . ', ' . $tgl_indo;
        }
        return $tgl_indo;
    }

    public function showJenis()
    {
        $jenises = JenisZakat::all()->where('nominal', '!=', 0);
        
        return view('zakat.jenis-zakat', compact('jenises'));
    }

    public function updateJenis($id)
    {
        $jenis = JenisZakat::findOrfail($id);
        $jenis->jenis = request('jenis');
        $jenis->nominal = request('nominal');
        $jenis->save();
        
        return redirect()->back()->withSuccess('Jenis Zakat '.$jenis->jenis.' Berhasil Dirubah');
    }

    public function getJenis($id)
    {
        $hasil = JenisZakat::findOrfail($id);
        return Response::json($hasil);
    }
}
