import React, { useState } from 'react'
import Table, { ItemTemplate } from '@webstollen/react-jtl-plugin/lib/components/Table'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'
import ReactTimeago from 'react-timeago'
import { faBolt, faLock, faTrash } from '@fortawesome/pro-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import Button from '@webstollen/react-jtl-plugin/lib/components/Button'

const Queue = () => {
  const [loading, setLoading] = useState(false)
  const [maxItems, setMaxItems] = useState(0)
  const api = useApi()

  const deleteQueue = (id: number) => {
    setLoading(true)
    api
      .run('queue', 'delete', { id: id })
      .then(() => alert('Bitte Tabelle neu laden!'))
      .catch(alert)
      .finally(() => setLoading(false))
  }

  const unlockQueue = (id: number) => {
    setLoading(true)
    api
      .run('queue', 'unlock', { id: id })
      .then(() => alert('Bitte Tabelle neu laden!'))
      .catch(alert)
      .finally(() => setLoading(false))
  }

  const runQueue = (id: number) => {
    setLoading(true)
    api
      .run('queue', 'run', { id: id })
      .then(() => alert('Bitte Tabelle neu laden!'))
      .catch(alert)
      .finally(() => setLoading(false))
  }

  const handleOnData = (page: number, itemsPerPage: number): Promise<any[]> => {
    setLoading(true)
    return new Promise((resolve, reject) => {
      api
        .run('queue', 'all', {
          query: 'SELECT * FROM xplugin_ws5_mollie_queue ORDER BY dCreated DESC',
          params: {
            ':limit': itemsPerPage,
            ':offset': page * itemsPerPage,
          },
        })
        .then((response: Record<string, any>) => {
          setMaxItems(response.data.data.maxItems)
          resolve(response.data.data.items)
          //console.debug(response.data.data.items)
        })
        .catch(reject)
        .finally(() => setLoading(false))
    })
  }

  const template = {
    kId: {
      header: () => 'ID',
      data: (row) => <>{row.kId}</>,
    },
    cType: {
      header: () => 'Type',
      data: (row) => <code>{row.cType}</code>,
    },
    cResult: {
      header: () => 'Result?',
      data: (row) => (
        <div className="truncate max-w-xs hover:overflow-clip hover:whitespace-pre-line">{row.cResult}</div>
      ),
    },
    cError: {
      header: () => 'Error?',
      data: (row) => (
        <div className="truncate max-w-xs hover:overflow-clip hover:whitespace-pre-line">{row.cError}</div>
      ),
    },
    dDone: {
      header: () => 'Done',
      data: (row) => (row.dDone ? <ReactTimeago date={row.dDone} /> : '-'),
    },
    dCreated: {
      header: () => 'Created',
      data: (row) => (row.dCreated ? <ReactTimeago date={row.dCreated} /> : '-'),
    },
    cActions: {
      header: () => ' ',
      data: (row) => (
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
              <ReactTimeago className="text-xs antialiased" date={row.bLock} />
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
      ),
    },
  } as Record<string, ItemTemplate<any>>

  const table = (
    <Table
      paginate={{
        maxPages: 2,
        maxItems: maxItems,
        itemsPerPage: 10,
      }}
      onData={handleOnData}
      template={template}
      striped
      loading={loading}
    />
  )

  return (
    <div className="relative container mx-auto">
      <div className="bg-white rounded-md p-1">{table}</div>
    </div>
  )
}

export default Queue
