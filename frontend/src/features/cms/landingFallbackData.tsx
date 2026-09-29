import React from 'react'
import { Wrench, Users, MapPinned, Headset } from 'lucide-react'

/**
 * Single isolated source of FRONTEND fallback content for the public landing
 * page. CMS (GET /cms/public) ALWAYS overrides these values field-by-field —
 * fallback never beats valid API data. Copy is realistic rental-business text.
 */

export const fallbackBrand = {
  brand_name: 'RAFA Rental',
}

export const fallbackHero = {
  eyebrow: 'CV SUMBER MAKMUR RAFA',
  title: 'Sewa Alat Berat untuk Proyek Anda',
  subtitle:
    'Solusi penyewaan alat berat untuk kebutuhan konstruksi, pertambangan, dan pekerjaan lapangan dengan proses yang praktis dan terpercaya.',
  cta_text: 'Cari Equipment',
  cta_link: '/app/equipment',
  image: '/hero-equipment.svg',
}

export const fallbackContent = {
  about:
    'Perusahaan penyedia layanan sewa alat berat untuk proyek konstruksi, tambang, dan infrastruktur — dengan proses transparan dan monitoring operasional presisi.',
  services: '',
  cta_section: 'Temukan armada yang sesuai kebutuhan pekerjaan Anda.',
  footer: 'PT RAFA Rental Nusantara. All rights reserved.',
}

export const stats = [
  { value: '100+', label: 'Unit Alat Berat' },
  { value: '50+', label: 'Proyek Terlayani' },
  { value: '100%', label: 'Kualitas Terjaga' },
  { value: '24/7', label: 'Dukungan Pelanggan' },
]

export const categories = ['Excavator', 'Bulldozer', 'Wheel Loader', 'Dump Truck', 'Crane', 'Compactor']

export const featuredFallback: Array<{ model: string; brand: string; price: string }> = [
  { model: 'Excavator PC200', brand: 'Komatsu', price: 'Rp 1.500.000 / hari' },
  { model: 'Wheel Loader WA320', brand: 'Komatsu', price: 'Rp 1.200.000 / hari' },
  { model: 'Dump Truck HD785', brand: 'Komatsu', price: 'Rp 2.500.000 / hari' },
  { model: 'Bulldozer D65', brand: 'Komatsu', price: 'Rp 1.800.000 / hari' },
  { model: 'Crane RT50', brand: 'Tadano', price: 'Rp 3.000.000 / hari' },
  { model: 'Compactor BW211', brand: 'Bomag', price: 'Rp 1.000.000 / hari' },
]

export const benefits: Array<{ icon: React.ReactNode; title: string; desc: string }> = [
  { icon: <Wrench size={18} />, title: 'Armada Terawat', desc: 'Unit menjalani perawatan berkala dan inspeksi kelayakan.' },
  { icon: <Users size={18} />, title: 'Operator Berpengalaman', desc: 'Didukung operator bersertifikasi dan berpengalaman.' },
  { icon: <MapPinned size={18} />, title: 'Pengiriman ke Lokasi', desc: 'Mobilisasi armada tepat waktu ke lokasi proyek Anda.' },
  { icon: <Headset size={18} />, title: 'Dukungan Operasional', desc: 'Penanganan cepat saat kendala operasional di lapangan.' },
]

export const fallbackNav: Array<{ label: string; href: string }> = [
  { label: 'Beranda', href: '/' },
  { label: 'Equipment', href: '/#equipment' },
  { label: 'Tentang Kami', href: '/#about' },
  { label: 'Kontak', href: '/#contact' },
]