import React, { useEffect, useState } from 'react'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faChevronDoubleDown, faChevronDoubleLeft, faSync } from '@fortawesome/pro-regular-svg-icons'
import useQueue from '../../../hooks/useQueue'
import DataTable, { DataTableHeader } from '@webstollen/react-jtl-plugin/lib/components/DataTable/DataTable'
import ReactTimeago from 'react-timeago'
import { Loading } from '@webstollen/react-jtl-plugin/lib'
import Alert from '@webstollen/react-jtl-plugin/lib/components/Alert'

type QueueProps = {
  id: string
}

const Queue = ({ id }: QueueProps) => {
  const [showLogs, setShowLogs] = useState(false)

  const { load, loading, data, error } = useQueue(id)

  useEffect(() => {
    load()
  }, [load])

  const reload = (e: React.MouseEvent) => {
    load()
    e.stopPropagation()
  }

  return (
    <div className="mt-4 relative">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowLogs((prev) => !prev)}>
        Queue {data?.length ? <>({data?.length})</> : null}{' '}
        <FontAwesomeIcon className=" float-right" icon={showLogs ? faChevronDoubleDown : faChevronDoubleLeft} />
        {showLogs && (
          <FontAwesomeIcon icon={faSync} onClick={reload} className="cursor-pointer float-right mx-2" fixedWidth />
        )}
      </h3>
      {showLogs &&
        (error ? (
          <Alert variant="error">{error}</Alert>
        ) : (
          <Loading loading={loading}>
            <DataTable striped fullWidth header={header}>
              {data?.map((row) => (
                <tr key={row.kId}>
                  <td>{row.kId}</td>
                  <td>{row.cType}</td>
                  <td>
                    <pre>{row.cResult ?? 'n/a'}</pre>
                  </td>
                  <td>
                    <pre>{row.cError ?? 'n/a'}</pre>
                  </td>
                  <td>{!row.dDone ? 'PENDING' : 'DONE'}</td>
                  <td>
                    <ReactTimeago date={row.dCreated} />
                  </td>
                  <td>{/*TODO: REDO*/}</td>
                </tr>
              ))}
            </DataTable>
          </Loading>
        ))}
    </div>
  )
}

const header: Array<DataTableHeader> = [
  {
    title: 'ID',
    column: 'id',
  },
  {
    title: 'Typ',
    column: 'typ',
  },
  {
    title: 'Result',
    column: 'result',
  },
  {
    title: 'Fehler?',
    column: 'error',
  },
  {
    title: 'Status',
    column: 'status',
  },
  {
    title: 'Erstellt',
    column: 'created',
  },
  {
    title: '',
    column: '_actions',
  },
]

export default Queue
