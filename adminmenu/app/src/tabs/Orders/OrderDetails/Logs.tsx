import React, { useCallback, useEffect, useState } from 'react'
import { Label, useAPI } from '@webstollen/react-jtl-plugin/lib'
import Table, { ItemTemplate } from '@webstollen/react-jtl-plugin/lib/components/Table'
import { faChevronDoubleDown, faChevronDoubleLeft } from '@fortawesome/pro-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'

export type LogsProps = {
  kBestellung: number
  orderId: string
}

const Logs = ({ kBestellung, orderId }: LogsProps) => {
  const api = useAPI()
  const [logs, setLogs] = useState<null | any[]>(null)
  const [showLogs, setShowLogs] = useState(false)

  const loadLogs = useCallback(() => {
    setLogs(null)
    api
      .run('orders', 'zalog', {
        kBestellung: kBestellung,
        id: orderId,
      })
      .then((result) => {
        setLogs(result.data)
      })
      .catch((err) => {
        console.log(err)
      })
  }, [api, kBestellung, orderId])

  useEffect(() => {
    loadLogs()
  }, [loadLogs])

  const template = {
    nLevel: {
      header: () => 'Level',
      data: (row) =>
        row.nLevel === 1 ? (
          <Label color="red">ERROR</Label>
        ) : row.nLevel == 2 ? (
          <Label color="blue">NOTICE</Label>
        ) : row.nLevel == 3 ? (
          <Label color="gray">DEBUG</Label>
        ) : (
          row.nLevel
        ),
    },
    cLog: {
      header: () => 'Log',
      data: (row) => row.cLog,
    },
    dDatum: {
      header: () => 'Timestamp',
      data: (row) => row.dDatum,
    },
  } as Record<string, ItemTemplate<Record<string, any>>>

  return (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowLogs((prev) => !prev)}>
        Logs
        <FontAwesomeIcon className="float-right" icon={showLogs ? faChevronDoubleDown : faChevronDoubleLeft} />
      </h3>
      {showLogs &&
        (!logs ? <>Loading...</> : !logs.length ? <>No Data!</> : <Table striped template={template} items={logs} />)}
    </div>
  )
}

export default Logs
