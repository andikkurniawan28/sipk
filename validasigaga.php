<?php

class ValidasiGagal extends Exception
{
    public string $judul;

    public function __construct(string $pesan, string $judul = 'Validasi Gagal')
    {
        parent::__construct($pesan);
        $this->judul = $judul;
    }
}