import { useCallback, useEffect, useState } from 'react'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'

export type UseMollieReturn = {
  loading: boolean
  data: null | Record<string, any>
  error: null | string
  load: () => void
  refundAmount: (amount: number) => Promise<void>
  cancelOrderLine: (orderLineId: string, quantity: number) => Promise<void>
  refundOrderLine: (orderLineId: string, quantity: number) => Promise<void>
  cancelOrder: () => Promise<void>
  refundOrder: () => Promise<void>
  cancelRefund: (id: string) => Promise<void>
}

const PluginAPI = useApi

const useMollie = (id: string, autoload = false): UseMollieReturn => {
  const [state, setState] = useState<Record<string, any>>({
    loading: false,
    error: null,
    data: null,
  })

  const load = useCallback(() => {
    const api = PluginAPI()
    setState((p) => ({ ...p, loading: true, error: null }))

    if (id.substring(0, 4) === 'ord_') {
      api
        .run('mollie', 'getOrder', {
          id: id,
        })
        .then((res) => setState((p) => ({ ...p, data: res.data.data })))
        .catch((e) => setState((p) => ({ ...p, error: `${e}` })))
        .finally(() => setState((p) => ({ ...p, loading: false })))
    } else if (id.substring(0, 3) === 'tr_') {
      api
        .run('mollie', 'getPayment', {
          id: id,
        })
        .then((res) => setState((p) => ({ ...p, data: res.data.data })))
        .catch((e) => setState((p) => ({ ...p, error: `${e}` })))
        .finally(() => setState((p) => ({ ...p, loading: false })))
    }
  }, [id, setState])

  const refundAmount = useCallback(
    async (amount: number) => {
      const api = PluginAPI()

      if (amount <= 0) {
        throw new Error('Betrag kann nicht kleiner oder gleich Null sein.')
      }
      setState((p) => ({ ...p, loading: true }))

      api
        .run('Mollie', 'refundAmount', {
          amount: amount,
          id: id,
        })
        .then(async (r) => {
          await load()
        })
        .finally(() => setState((p) => ({ ...p, loading: false })))
    },
    [id, load, setState]
  )

  const cancelOrderLine = useCallback(
    async (lineId: string, quantity: number) => {
      const api = PluginAPI()
      if (quantity <= 0) {
        throw new Error('Anzahl kann nicht kleiner oder gleich Null sein.')
      }
      setState((p) => ({ ...p, loading: true }))
      api
        .run('mollie', 'cancelOrderLine', {
          id: id,
          lineId: lineId,
          quantity: quantity,
        })
        .then(async (r) => {
          if (!r.data?.error?.message) {
            await load()
          } else {
            alert(r.data?.error?.message)
          }
        })
        .finally(() => setState((p) => ({ ...p, loading: false })))
    },
    [id, load, setState]
  )

  const cancelOrder = useCallback(async () => {
    const api = PluginAPI()
    setState((p) => ({ ...p, loading: true }))
    api
      .run('mollie', 'cancelOrder', {
        id: id,
      })
      .then(async (r) => {
        if (!r.data?.error?.message) {
          await load()
        } else {
          alert(r.data?.error?.message)
        }
      })
      .finally(() => setState((p) => ({ ...p, loading: false })))
  }, [id, load, setState])

  const refundOrder = useCallback(async () => {
    const api = PluginAPI()
    setState((p) => ({ ...p, loading: true }))
    api
      .run('mollie', 'refundOrder', {
        id: id,
      })
      .then(async (r) => {
        if (!r.data?.error?.message) {
          await load()
        } else {
          alert(r.data?.error?.message)
        }
      })
      .finally(() => setState((p) => ({ ...p, loading: false })))
  }, [id, load, setState])

  const refundOrderLine = useCallback(
    async (lineId: string, quantity: number) => {
      const api = PluginAPI()
      if (quantity <= 0) {
        throw new Error('Anzahl kann nicht kleiner oder gleich Null sein.')
      }
      setState((p) => ({ ...p, loading: true }))

      api
        .run('mollie', 'refundOrderLine', {
          id: id,
          lineId: lineId,
          quantity: quantity,
        })
        .then(async (r) => {
          await load()
        })
        .finally(() => setState((p) => ({ ...p, loading: false })))
    },
    [id, load, setState]
  )

  const cancelRefund = useCallback(
    async (refundId: string) => {
      const api = PluginAPI()
      if (refundId.trim()) {
        setState((p) => ({ ...p, loading: true }))

        api
          .run('mollie', 'cancelRefund', {
            id: id,
            refundId: refundId,
          })
          .then(async (r) => {
            await load()
          })
          .finally(() => setState((p) => ({ ...p, loading: false })))
      }
    },
    [id, load, setState]
  )

  useEffect(() => {
    if (autoload) {
      load()
    }
  }, [autoload, load])

  return {
    loading: state.loading,
    data: state.data,
    error: state.error,
    load: load,
    refundAmount: refundAmount,
    cancelOrderLine: cancelOrderLine,
    refundOrderLine: refundOrderLine,
    cancelOrder: cancelOrder,
    refundOrder: refundOrder,
    cancelRefund: cancelRefund,
  }
}

export default useMollie
