import { useCallback, useState } from 'react'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'

export type UseQueuesReturn = {
  loading: boolean
  data: null | {
    maxItems: number
    items: Array<Record<string, any>>
  }
  error: null | string
  load: (page: number, itemsPerPage: number, query?: string) => Promise<void>
}

const PluginAPI = useApi

const useQueues = (): UseQueuesReturn => {
  const [state, setState] = useState<Record<string, any>>({
    loading: false,
    error: null,
    data: null,
  })

  const load = useCallback(
    async (page: number, perPage: number, query?: string) => {
      const api = PluginAPI()
      setState((p) => ({ ...p, loading: true, error: null }))

      const offset = perPage * page
      let params: Record<string, any> = { ':limit': perPage, ':offset': offset }
      let sqlQuery = 'SELECT * FROM xplugin_ws5_mollie_queue ORDER BY dCreated DESC'

      if (query && query.trim() !== '') {
        sqlQuery =
          'SELECT * FROM xplugin_ws5_mollie_queue ' +
          'WHERE cType LIKE :query1 ' +
          'OR cResult LIKE :query2 ' +
          'OR cError LIKE :query3 ' +
          'ORDER BY dCreated DESC'
        params[':query1'] = params[':query2'] = params[':query3'] = `%${query}%`
      }

      api
        .run('queue', 'all', {
          query: sqlQuery,
          params: params,
        })
        .then((res) => {
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
