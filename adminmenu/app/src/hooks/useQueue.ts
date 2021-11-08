import { useCallback, useState } from 'react'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'

const PluginAPI = useApi

export type UseQueueReturn = {
  loading: boolean
  data: null | Array<Record<string, any>>
  error: null | string
  load: () => Promise<void>
}

const useQueue = (id: string): UseQueueReturn => {
  const [state, setState] = useState<Record<string, any>>({
    loading: false,
    error: null,
    data: null,
  })

  const load = useCallback(async () => {
    const api = PluginAPI()
    console.debug('(useQueue->load)')
    setState((p) => ({ ...p, loading: true, error: null }))
    api
      .run('Orders', 'getQueue', {
        id: id,
      })
      .then((res) => {
        console.debug('(useQueue->load) Queue loaded', res)
        setState((p) => ({ ...p, data: res.data.data }))
      })
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

export default useQueue
