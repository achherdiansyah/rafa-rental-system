import React from 'react'
import { WifiOff, RotateCcw } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { cn } from '@/utils/cn'

export interface NetworkErrorBannerProps {
  onRetry?: () => void
  message?: string
  className?: string
}

export const NetworkErrorBanner: React.FC<NetworkErrorBannerProps> = ({
  onRetry,
  message = 'Tidak dapat terhubung ke server backend. Pastikan server aktif dan koneksi internet stabil.',
  className,
}) => {
  return (
    <div
      role="alert"
      className={cn(
        'flex flex-col sm:flex-row items-center justify-between gap-3 p-4 rounded-xl border border-amber-300 bg-amber-50 text-amber-900 text-sm shadow-xs',
        className
      )}
    >
      <div className="flex items-center gap-3">
        <div className="p-2 rounded-lg bg-amber-100 text-amber-700 shrink-0">
          <WifiOff size={18} />
        </div>
        <div>
          <p className="font-semibold">Gangguan Koneksi Jaringan</p>
          <p className="text-xs text-amber-800 mt-0.5">{message}</p>
        </div>
      </div>

      {onRetry && (
        <Button
          variant="outline"
          size="sm"
          onClick={onRetry}
          leftIcon={<RotateCcw size={14} />}
          className="border-amber-300 bg-white text-amber-900 hover:bg-amber-100 shrink-0"
        >
          Coba Hubungkan Ulang
        </Button>
      )}
    </div>
  )
}
