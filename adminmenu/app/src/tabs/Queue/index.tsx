import React, { useCallback, useEffect, useState } from 'react'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'
import useQueues from '../../hooks/useQueues'
import useErrorSnack from '../../hooks/useErrorSnack'
import DataTable, { DataTableHeader } from '@webstollen/react-jtl-plugin/lib/components/DataTable/DataTable'
import Button from '@webstollen/react-jtl-plugin/lib/components/Button'
import { faBolt, faLock, faTrash } from '@fortawesome/pro-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import Alert from '@webstollen/react-jtl-plugin/lib/components/Alert'
import TextLink from '@webstollen/react-jtl-plugin/lib/components/TextLink'

const Queue = () => {
  const [loading, setLoading] = useState(false)
  const api = useApi()
  const [showError] = useErrorSnack()
  const { data, load, error, loading: queueLoading } = useQueues()
  const [queuesState, setQueuesState] = useState({
    page: 0,
    perPage: 10,
    query: '',
  })

  const reload = useCallback(
    async () => await load(queuesState.page, queuesState.perPage, queuesState.query),
    [load, queuesState.page, queuesState.perPage, queuesState.query]
  )

  useEffect(() => {
    load(queuesState.page, queuesState.perPage, queuesState.query)
  }, [load, queuesState.page, queuesState.perPage, queuesState.query])

  const deleteQueue = (id: number) => {
    setLoading(true)
    api
      .run('queue', 'delete', { id: id })
      .then(reload)
      .catch(showError)
      .finally(() => setLoading(false))
  }

  const unlockQueue = (id: number) => {
    setLoading(true)
    api
      .run('queue', 'unlock', { id: id })
      .then(reload)
      .catch(showError)
      .finally(() => setLoading(false))
  }

  const runQueue = (id: number) => {
    setLoading(true)
    api
      .run('queue', 'run', { id: id })
      .then(reload)
      .catch(showError)
      .finally(() => setLoading(false))
  }

  const header: Array<DataTableHeader> = [
    {
      title: 'ID',
      column: 'kID',
    },
    {
      title: 'Type',
      column: 'cType',
    },
    {
      title: 'Result?',
      column: 'cResult',
    },
    {
      title: 'Error?',
      column: 'cError',
    },
    {
      title: 'Erledigt',
      column: 'dDone',
    },
    {
      title: 'Erstellt',
      column: 'dCreated',
    },
    {
      title: '',
      column: '_actions',
    },
  ]

  const handleTableChange = async (page: number, perPage: number) => {
    if (page === queuesState.page && perPage === queuesState.perPage) {
      await load(queuesState.page, queuesState.perPage)
    } else {
      setQueuesState((p) => ({ ...p, page: page, perPage: perPage }))
    }
  }

  const handleSearch = useCallback(
    (query: string) => {
      if (query !== queuesState.query) {
        setQueuesState((p) => ({ ...p, query: query, page: 0 }))
      }
    },
    [setQueuesState, queuesState.query]
  )

  const table = error ? (
    <Alert variant="error">
      {error}{' '}
      <TextLink color="red" onClick={reload}>
        Erneut versuchen!
      </TextLink>
    </Alert>
  ) : (
    <DataTable
      loading={loading || queueLoading}
      header={header}
      onSearch={handleSearch}
      fullWidth
      striped
      pagination={{
        page: queuesState.page,
        total: data?.maxItems ?? 0,
        perPage: queuesState.perPage,
        onChange: handleTableChange,
      }}
    >
      {data?.items?.map((row) => (
        <tr key={row.kId}>
          <td>{row.kId}</td>
          <td>
            <code>{row.cType}</code>
          </td>
          <td>
            <div className="truncate max-w-xs hover:overflow-clip hover:whitespace-pre-line">{row.cResult}</div>
          </td>
          <td>
            <div className="truncate max-w-xs hover:overflow-clip hover:whitespace-pre-line">{row.cError}</div>
          </td>
          <td>{row.dDone ? <>{new Date(row.dDone).toLocaleDateString('de-DE')} {new Date(row.dDone).toLocaleTimeString('de-DE')}</> : '-'}</td>
          <td>{row.dCreated ? <>{new Date(row.dCreated).toLocaleDateString('de-DE')} {new Date(row.dCreated).toLocaleTimeString('de-DE')}</> : '-'}</td>
          <td>
            <div className="flex text-center justify-center items-center">
              {row.bLock ? (
                <div className="flex flex-col text-center  items-center ">
                  <Button
                    onClick={() => (window.confirm('Wirklich entsperren?') ? unlockQueue(row.kId) : null)}
                    color="orange"
                    title="Unlock!"
                    className="cursor-pointer p-1 ml-1"
                  >
                    <FontAwesomeIcon fixedWidth icon={faLock} />
                  </Button>
                  {new Date(row.bLock).toLocaleDateString('de-DE')} {new Date(row.bLock).toLocaleTimeString('de-DE')}
                </div>
              ) : (
                <Button
                  onClick={() => (window.confirm('Wirklich erneut ausführen?') ? runQueue(row.kId) : null)}
                  className="ml-1"
                  color="blue"
                  title="Run again!"
                >
                  <FontAwesomeIcon fixedWidth icon={faBolt} />
                </Button>
              )}
              <Button
                onClick={() => (window.confirm('Wirklich löschen?') ? deleteQueue(row.kId) : null)}
                title="Delete"
                className="ml-1"
                color="red"
              >
                <FontAwesomeIcon fixedWidth icon={faTrash} />
              </Button>
            </div>
          </td>
        </tr>
      ))}
    </DataTable>
  )

  return (
    <div className="relative container mx-auto">
      <div className="bg-white rounded-md p-1">{table}</div>
    </div>
  )
}

export default Queue
