import React, { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ShoppingCart } from 'lucide-react'
import { cartService } from './services/cartService'

export const CartBadge: React.FC = () => {
  const navigate = useNavigate()
  const [unitCount, setUnitCount] = useState(0)

  const load = async () => {
    try {
      const res = await cartService.getCart()
      if (res.success && res.data) {
        // Calculate total quantity of UNITS, not total rows
        const count = res.data.items.reduce((sum, item) => sum + item.quantity, 0)
        setUnitCount(count)
      } else {
        setUnitCount(0)
      }
    } catch {
      setUnitCount(0)
    }
  }

  useEffect(() => {
    load()
    const onChanged = () => load()
    window.addEventListener('rafa:cart-changed', onChanged)
    window.addEventListener('focus', load)
    return () => {
      window.removeEventListener('rafa:cart-changed', onChanged)
      window.removeEventListener('focus', load)
    }
  }, [])

  return (
    <button
      type="button"
      onClick={() => navigate('/app/cart')}
      aria-label={`Keranjang Sewa, ${unitCount} unit`}
      className="relative p-2 rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-50 transition-colors cursor-pointer"
    >
      <ShoppingCart size={19} />
      {unitCount > 0 && (
        <span className="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[17px] h-[17px] px-1 rounded-full text-[10px] font-bold text-white bg-primary-500">
          {unitCount > 99 ? '99+' : unitCount}
        </span>
      )}
    </button>
  )
}
