import { subtotalCents, saleTotalCents } from '~/utils/cartMath'
import type { DiscountType } from '~/utils/cartMath'

export interface CartItem {
  key: string
  productVariationId: number
  productName: string
  variationLabel: string | null
  productCode: string
  unitPrice: number
  quantity: number
  discountType: DiscountType
  discountValue: number
  availableQuantity: number
  wholesaleMinQty: number | null
  wholesalePrice: number | null
  applyWholesale: boolean
}

export interface SaleItem {
  id: number
  product_variation_id: number
  product_name: string | null
  product_code: string | null
  quantity: number
  unit_price: string
  discount_type: DiscountType
  discount_type_label: string
  discount_value: string
  discount: string
  total: string
}

export interface Sale {
  id: number
  number: string
  customer_id: number | null
  customer_name: string | null
  seller_id: number
  seller_name: string | null
  cash_register_id: number
  subtotal: string
  discount_type: DiscountType
  discount_type_label: string
  discount_value: string
  discount: string
  total: string
  payment_method_id: number
  payment_method_name: string | null
  notes: string | null
  status: 'pending' | 'completed' | 'canceled' | 'converted'
  status_label: string
  expires_at: string | null
  converted_to_sale_id: number | null
  converted_to_sale_number: string | null
  items: SaleItem[]
  created_at: string
}

// Preço efetivo de um item: só usa o preço de atacado quando o operador marcou
// o checkbox E a quantidade ainda bate o mínimo — nunca automático (espelha a
// regra do RegisterSaleAction no backend, que exige `apply_wholesale` explícito).
function effectiveUnitPrice(item: Pick<CartItem, 'unitPrice' | 'quantity' | 'applyWholesale' | 'wholesaleMinQty' | 'wholesalePrice'>): number {
  if (item.applyWholesale && item.wholesaleMinQty !== null && item.wholesalePrice !== null && item.quantity >= item.wholesaleMinQty) {
    return item.wholesalePrice
  }
  return item.unitPrice
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [] as CartItem[],
    customerId: null as number | null,
    customerName: null as string | null,
    sellerId: null as number | null,
    paymentMethodId: null as number | null,
    saleDiscountType: 'percentage' as DiscountType,
    saleDiscountValue: 0,
    notes: null as string | null,
    expiresAt: null as string | null,
  }),

  getters: {
    subtotal: (state) => subtotalCents(state.items.map((item) => ({
      unitPrice: effectiveUnitPrice(item),
      quantity: item.quantity,
      discountType: item.discountType,
      discountValue: item.discountValue,
    }))) / 100,

    total(state): number {
      const subtotalC = subtotalCents(state.items.map((item) => ({
        unitPrice: effectiveUnitPrice(item),
        quantity: item.quantity,
        discountType: item.discountType,
        discountValue: item.discountValue,
      })))

      return saleTotalCents(subtotalC, state.saleDiscountType, state.saleDiscountValue) / 100
    },

    itemCount: (state) => state.items.reduce((sum, item) => sum + item.quantity, 0),
    isEmpty: (state) => state.items.length === 0,
    effectivePrice: () => (item: CartItem) => effectiveUnitPrice(item),
  },

  actions: {
    addItem(variation: {
      id: number
      productName: string
      variationLabel: string | null
      productCode: string
      salePrice: number
      currentQuantity: number
      wholesaleMinQty?: number | null
      wholesalePrice?: number | null
    }, quantity = 1) {
      const existing = this.items.find((item) => item.productVariationId === variation.id)
      if (existing) {
        existing.quantity += quantity
        return
      }

      this.items.push({
        key: String(variation.id),
        productVariationId: variation.id,
        productName: variation.productName,
        variationLabel: variation.variationLabel,
        productCode: variation.productCode,
        unitPrice: variation.salePrice,
        quantity,
        discountType: 'percentage',
        discountValue: 0,
        availableQuantity: variation.currentQuantity,
        wholesaleMinQty: variation.wholesaleMinQty ?? null,
        wholesalePrice: variation.wholesalePrice ?? null,
        applyWholesale: false,
      })
    },

    setItemWholesale(key: string, apply: boolean) {
      const item = this.items.find((i) => i.key === key)
      if (item) item.applyWholesale = apply
    },

    updateQuantity(key: string, quantity: number) {
      if (quantity <= 0) {
        this.removeItem(key)
        return
      }
      const item = this.items.find((i) => i.key === key)
      if (item) item.quantity = quantity
    },

    updateItemDiscount(key: string, type: DiscountType, value: number) {
      const item = this.items.find((i) => i.key === key)
      if (item) {
        item.discountType = type
        item.discountValue = Math.max(0, value)
      }
    },

    removeItem(key: string) {
      this.items = this.items.filter((item) => item.key !== key)
    },

    setCustomer(id: number | null, name: string | null = null) {
      this.customerId = id
      this.customerName = name
    },

    setSeller(id: number | null) {
      this.sellerId = id
    },

    setPaymentMethod(id: number | null) {
      this.paymentMethodId = id
    },

    setSaleDiscount(type: DiscountType, value: number) {
      this.saleDiscountType = type
      this.saleDiscountValue = Math.max(0, value)
    },

    setNotes(value: string | null) {
      this.notes = value
    },

    setExpiresAt(value: string | null) {
      this.expiresAt = value
    },

    reset() {
      this.items = []
      this.customerId = null
      this.customerName = null
      this.paymentMethodId = null
      this.saleDiscountType = 'percentage'
      this.saleDiscountValue = 0
      this.notes = null
      this.expiresAt = null
    },

    async checkout() {
      const api = useApi()
      const { data } = await api<{ data: Sale }>('/sales', {
        method: 'POST',
        body: {
          customer_id: this.customerId,
          seller_id: this.sellerId,
          payment_method_id: this.paymentMethodId,
          discount_type: this.saleDiscountType,
          discount_value: this.saleDiscountValue,
          notes: this.notes,
          items: this.items.map((item) => ({
            product_variation_id: item.productVariationId,
            quantity: item.quantity,
            apply_wholesale: item.applyWholesale,
            discount_type: item.discountType,
            discount_value: item.discountValue,
          })),
        },
      })

      this.reset()
      return data
    },

    async saveAsQuote() {
      const api = useApi()
      const { data } = await api<{ data: Sale }>('/quotes', {
        method: 'POST',
        body: {
          customer_id: this.customerId,
          seller_id: this.sellerId,
          expires_at: this.expiresAt,
          discount_type: this.saleDiscountType,
          discount_value: this.saleDiscountValue,
          notes: this.notes,
          items: this.items.map((item) => ({
            product_variation_id: item.productVariationId,
            quantity: item.quantity,
            apply_wholesale: item.applyWholesale,
            discount_type: item.discountType,
            discount_value: item.discountValue,
          })),
        },
      })

      this.reset()
      return data
    },
  },
})
