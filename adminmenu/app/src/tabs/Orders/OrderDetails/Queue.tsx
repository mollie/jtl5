import React, { useState } from 'react'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faChevronDoubleDown, faChevronDoubleLeft } from '@fortawesome/pro-regular-svg-icons'
import { UseQueueReturn } from '../../../hooks/useQueue'
import DataTable, { DataTableHeader } from '@webstollen/react-jtl-plugin/lib/components/DataTable/DataTable'
import ReactTimeago from 'react-timeago'

type QueueProps = {
  queue: UseQueueReturn
}

const Queue = ({ queue }: QueueProps) => {
  const [showLogs, setShowLogs] = useState(false)

  return queue.data?.length ? (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowLogs((prev) => !prev)}>
        Queue {queue.data?.length ? <>({queue.data?.length})</> : null}
        <FontAwesomeIcon className=" float-right" icon={showLogs ? faChevronDoubleDown : faChevronDoubleLeft} />
      </h3>
      {showLogs && (
        <DataTable striped fullWidth header={header}>
          {queue.data.length &&
            queue.data.map((row) => (
              <tr>
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
              </tr>
            ))}
        </DataTable>
      )}
    </div>
  ) : null
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
