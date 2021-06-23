import React, { useState } from 'react'
import Table, { ItemTemplate } from '@webstollen/react-jtl-plugin/lib/components/Table'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faChevronDoubleDown, faChevronDoubleLeft } from '@fortawesome/pro-regular-svg-icons'

type QueueProps = {
  data: Array<Record<string, any>>
}

const Queue = ({ data }: QueueProps) => {
  const [showLogs, setShowLogs] = useState(false)

  const template = {
    kId: {
      header: () => 'ID',
      data: (row) => row.kId,
    },
    cType: {
      header: () => 'Typ',
      data: (row) => row.cType,
    },
    cResult: {
      header: () => 'Result',
      data: (row) => <pre>{row.cResult ?? 'n/a'}</pre>,
    },
    dDone: {
      header: () => 'Status',
      data: (row) => (!row.dDone ? 'PENDING' : 'DONE'),
      align: 'center',
    },
    dCreated: {
      header: () => 'Erstellt',
      data: (row) => row.dCreated,
      align: 'right',
    },
  } as Record<string, ItemTemplate<Record<string, any>>>

  return data.length ? (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowLogs((prev) => !prev)}>
        Queue {data.length ? <>({data.length})</> : null}
        <FontAwesomeIcon className=" float-right" icon={showLogs ? faChevronDoubleDown : faChevronDoubleLeft} />
      </h3>
      {showLogs ? <Table template={template} items={data} /> : null}
    </div>
  ) : null
}

export default Queue
