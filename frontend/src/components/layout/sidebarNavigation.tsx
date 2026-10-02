import {
  LayoutDashboard,
  Truck,
  Sparkles,
  ShoppingCart,
  MapPin,
  CalendarCheck,
  ClipboardList,
  Clock,
  Receipt,
  RotateCcw,
  Wallet,
  Bell,
  User,
  CheckSquare,
  Layers,
  DollarSign,
  Building2,
  CreditCard,
  Users,
  UserCheck,
  Table2,
  PiggyBank,
  Globe,
  TrendingUp,
  History,
  Settings,
} from 'lucide-react'
import type { SidebarGroup } from './sidebar/RoleSidebar'

export const userMenu: SidebarGroup[] = [
  {
    title: 'Utama',
    items: [
      { label: 'Dashboard', href: '/app', icon: <LayoutDashboard size={18} /> },
      { label: 'Katalog Alat', href: '/app/equipment', icon: <Truck size={18} /> },
      { label: 'Rekomendasi', href: '/app/recommendations', icon: <Sparkles size={18} /> },
      { label: 'Keranjang', href: '/app/cart', icon: <ShoppingCart size={18} /> },
    ],
  },
  {
    title: 'Penyewaan',
    items: [
      { label: 'Lokasi Proyek', href: '/app/locations', icon: <MapPin size={18} /> },
      { label: 'Sewa Saya', href: '/app/bookings', icon: <CalendarCheck size={18} /> },
      { label: 'Rental Saya', href: '/app/rentals', icon: <ClipboardList size={18} /> },
      { label: 'Timesheet Harian', href: '/app/timesheets', icon: <Clock size={18} /> },
    ],
  },
  {
    title: 'Keuangan',
    items: [
      { label: 'Tagihan & Bayar', href: '/app/invoices', icon: <Receipt size={18} /> },
      { label: 'Refund Saya', href: '/app/refunds', icon: <RotateCcw size={18} /> },
      { label: 'Outstanding', href: '/app/outstanding', icon: <Wallet size={18} /> },
    ],
  },
  {
    title: 'Lainnya',
    items: [
      { label: 'Notifikasi', href: '/app/notifications', icon: <Bell size={18} />, badge: 0 },
      { label: 'Profil', href: '/app/profile', icon: <User size={18} /> },
    ],
  },
]

export const adminMenu: SidebarGroup[] = [
  {
    title: 'Operasional',
    items: [
      { label: 'Dashboard', href: '/admin', icon: <LayoutDashboard size={18} /> },
      { label: 'Approval Booking', href: '/admin/bookings', icon: <CheckSquare size={18} /> },
      { label: 'Eksekusi Rental', href: '/admin/rentals', icon: <ClipboardList size={18} /> },
      { label: 'Validasi Timesheet', href: '/admin/timesheets', icon: <Clock size={18} /> },
    ],
  },
  {
    title: 'Pengguna',
    items: [
      { label: 'Verifikasi Akun', href: '/admin/users', icon: <UserCheck size={18} /> },
    ],
  },
  {
    title: 'Master Data',
    items: [
      { label: 'Armada & Tipe', href: '/admin/equipment', icon: <Layers size={18} /> },
      { label: 'Unit Fisik', href: '/admin/units', icon: <Truck size={18} /> },
      { label: 'Tarif & Harga', href: '/admin/pricing', icon: <DollarSign size={18} /> },
      { label: 'Rekening Perusahaan', href: '/admin/banks', icon: <Building2 size={18} /> },
    ],
  },
  {
    title: 'Keuangan',
    items: [
      { label: 'Invoice', href: '/admin/invoices', icon: <Receipt size={18} /> },
      { label: 'Verifikasi Pembayaran', href: '/admin/payments', icon: <CreditCard size={18} /> },
      { label: 'Refund', href: '/admin/refunds', icon: <RotateCcw size={18} /> },
      { label: 'Outstanding', href: '/admin/outstanding', icon: <Users size={18} /> },
    ],
  },
  {
    title: 'Laporan',
    items: [
      { label: 'Laporan Operasional', href: '/admin/reports/operational', icon: <Table2 size={18} /> },
      { label: 'Laporan Finansial', href: '/admin/reports/financial', icon: <PiggyBank size={18} /> },
    ],
  },
  {
    title: 'Website',
    items: [{ label: 'CMS Landing Page', href: '/admin/cms', icon: <Globe size={18} /> }],
  },
]

export const ownerMenu: SidebarGroup[] = [
  {
    title: 'Governance',
    items: [
      { label: 'Executive Summary', href: '/owner', icon: <LayoutDashboard size={18} /> },
      { label: 'Laporan Pendapatan', href: '/owner/revenue', icon: <TrendingUp size={18} /> },
      { label: 'Audit Trail Logs', href: '/owner/audit', icon: <History size={18} /> },
      { label: 'Rekening & Kontrol', href: '/owner/settings', icon: <Settings size={18} /> },
    ],
  },
]