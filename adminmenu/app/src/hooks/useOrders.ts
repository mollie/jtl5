import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'
import { useCallback, useState } from 'react'

const PluginAPI = useApi

export type UseOrdersReturn = {
  loading: boolean
  data: null | {
    maxItems: number
    items: Array<Record<string, any>>
  }
  error: null | string
  load: (perPage: number, page: number) => Promise<void>
}

const useOrders = (): UseOrdersReturn => {
  const [state, setState] = useState<Record<string, any>>({
    loading: false,
    error: null,
    data: null,
  })

  const load = useCallback(async (page: number, perPage: number) => {
    const api = PluginAPI()
    console.debug('(useOrders->load)')
    setState((p) => ({ ...p, loading: true, error: null }))

    const offset = perPage * page

    api
      .run('orders', 'all', {
        query:
          'SELECT o.*, b.cStatus as cJTLStatus, b.cAbgeholt, b.cVersandartName, b.cZahlungsartName, b.fGuthaben, b.fGesamtsumme FROM xplugin_ws5_mollie_orders o JOIN tbestellung b ON b.kbestellung = o.kBestellung ORDER BY b.dErstellt DESC;',
        params: { ':limit': perPage, ':offset': offset },
      })
      .then((res) => {
        console.debug('(useOrders->load) Orders loaded', res)
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
  }
}

export default useOrders
