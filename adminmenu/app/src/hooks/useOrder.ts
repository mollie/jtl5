import { useCallback, useState } from 'react'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'

export type UseOrderReturn = {
  loading: boolean
  data: null | Record<string, any>
  error: null | string
  load: () => void
}

const PluginAPI = useApi

const useOrder = (id: string): UseOrderReturn => {
  const [state, setState] = useState<Record<string, any>>({
    loading: false,
    error: null,
    data: null,
  })

  const load = useCallback(() => {
    const api = PluginAPI()
    setState((p) => ({ ...p, loading: true, error: null }))
    api
      .run('orders', 'get', {
        id: id,
      })
      .then((res) => setState((p) => ({ ...p, data: res.data.data })))
      .catch((e) => setState((p) => ({ ...p, error: `${e}` })))
      .finally(() => setState((p) => ({ ...p, loading: false })))
  }, [id, setState])

  return {
    loading: state.loading,
    data: state.data,
    error: state.error,
    load: load,
  }
}

export default useOrder
