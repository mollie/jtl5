import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'
import { useCallback, useState } from 'react'

const PluginAPI = useApi

export type UseShipmentsReturn = {
  loading: boolean
  data: null | Array<Record<string, any>>
  error: null | string
  load: () => Promise<void>
  sync: (mollieId: string, kLieferschein: number) => Promise<Record<string, any>>
}

const useShipments = (kBestellung: number): UseShipmentsReturn => {
  const [state, setState] = useState<Record<string, any>>({
    loading: false,
    error: null,
    data: null,
  })

  const sync = useCallback(async (mollieId: string, kLieferschein: number) => {
    const api = PluginAPI()
    return await api.run('shipments', 'sync', {
      kLieferschein: kLieferschein,
      orderId: mollieId,
      kBestellung: kBestellung,
    })
  }, [])

  const load = useCallback(async () => {
    const api = PluginAPI()
    console.debug('(useShipments->load)')
    setState((p) => ({ ...p, loading: true, error: null }))

    api
      .run('orders', 'shipments', {
        kBestellung: kBestellung,
      })
      .then((res) => {
        console.debug('(useShipments->load) Shipments loaded', res)
        setState((p) => ({ ...p, data: res.data.data }))
      })
      .catch((e) => setState((p) => ({ ...p, error: `${e}` })))
      .finally(() => setState((p) => ({ ...p, loading: false })))
  }, [])

  return {
    loading: state.loading,
    error: state.error,
    data: state.data,
    load: load,
    sync: sync,
  }
}

export default useShipments
