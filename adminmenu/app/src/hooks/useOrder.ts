import { useCallback, useState } from 'react'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'

export type UseOrderReturn = {
  loading: boolean
  data: null | Record<string, any>
  error: null | string
  load: () => Promise<void>
}

const PluginAPI = useApi

const useOrder = (id: string): UseOrderReturn => {
  const [state, setState] = useState<Record<string, any>>({
    loading: false,
    error: null,
    data: null,
  })

  const load = useCallback(async () => {
    const api = PluginAPI()
    setState((p) => ({ ...p, loading: true, error: null }))

    if (id.substring(0, 4) === 'ord_') {
      api
        .run('Orders', 'get', {
          id: id,
        })
        .then((res) => {
          setState((p) => ({ ...p, data: res.data.data }))
        })
        .catch((e) => setState((p) => ({ ...p, error: `${e}` })))
        .finally(() => setState((p) => ({ ...p, loading: false })))
    } else if (id.substring(0, 3) === 'tr_') {
      api
        .run('mollie', 'getPayment', {
          id: id,
        })
        .then((res) => {
          setState((p) => ({ ...p, data: res.data.data }))
        })
        .catch((e) => setState((p) => ({ ...p, error: `${e}` })))
        .finally(() => setState((p) => ({ ...p, loading: false })))
    }
  }, [id, setState])

  return {
    loading: state.loading,
    data: state.data,
    error: state.error,
    load: load,
  }
}

export default useOrder
