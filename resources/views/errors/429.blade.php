@extends('errors.layout')

@section('code', '429')
@section('icon', 'fa-gauge-high')
@section('eyebrow', 'Permintaan dibatasi')
@section('title', 'Terlalu banyak aktivitas')
@section('message', 'CMS menerima terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar sebelum mencoba tindakan yang sama kembali.')
@section('hint', 'Hindari menekan tombol simpan atau memuat ulang halaman berulang kali.')
