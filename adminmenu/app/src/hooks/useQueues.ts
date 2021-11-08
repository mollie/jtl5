import { useCallback, useState } from 'react'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'

export type UseQueuesReturn = {
  loading: boolean
  data: null | {
    maxItems: number
    items: Array<Record<string, any>>
  }
  error: null | string
  load: (page: number, itemsPerPage: number) => Promise<void>
}

const PluginAPI = useApi

const useQueues = (): UseQueuesReturn => {
  const [state, setState] = useState<Record<string, any>>({
    loading: false,
    error: null,
    data: null,
  })

  const load = useCallback(
    async (page: number, itemsPerPage: number) => {
      const api = PluginAPI()
      console.debug('(useQueues->load)')
      setState((p) => ({ ...p, loading: true, error: null }))
      api
        .run('queue', 'all', {
          query: 'SELECT * FROM xplugin_ws5_mollie_queue ORDER BY dCreated DESC',
          params: {
            ':limit': itemsPerPage,
            ':offset': page * itemsPerPage,
          },
        })
        .then((res) => {
          console.debug('(useQueues->load) Queues loaded', res)
          setState((p) => ({ ...p, data: res.data.data }))
        })
        .catch((e) => setState((p) => ({ ...p, error: `${e}` })))
        .finally(() => setState((p) => ({ ...p, loading: false })))
    },
    [setState]
  )

  return {
    loading: state.loading,
    data: state.data,
    error: state.error,
    load: load,
  }
}

export default useQueues
