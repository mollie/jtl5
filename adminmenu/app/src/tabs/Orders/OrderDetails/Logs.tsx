import React, { useEffect, useState } from 'react'
import { Label } from '@webstollen/react-jtl-plugin/lib'
import { faChevronDoubleDown, faChevronDoubleLeft } from '@fortawesome/pro-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { UseOrderReturn } from '../../../hooks/useOrder'
import { UseMollieReturn } from '../../../hooks/useMollie'
import usePaymentLogs from '../../../hooks/usePaymentLogs'
import DataTable, { DataTableHeader } from '@webstollen/react-jtl-plugin/lib/components/DataTable/DataTable'
import ReactTimeago from 'react-timeago'

export type LogsProps = {
  order: UseOrderReturn
  mollie: UseMollieReturn
}

const Logs = ({ order, mollie }: LogsProps) => {
  const [showLogs, setShowLogs] = useState(false)
  const logs = usePaymentLogs(order.data?.kBestellung, mollie.data?.id)

  useEffect(() => {
    logs.load()
  }, [logs.load])

  return (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowLogs((prev) => !prev)}>
        Logs
        <FontAwesomeIcon className="float-right" icon={showLogs ? faChevronDoubleDown : faChevronDoubleLeft} />
      </h3>
      {showLogs &&
        (!logs.data ? (
          <>Loading...</>
        ) : !logs.data?.length ? (
          <>No Data!</>
        ) : (
          <DataTable striped fullWidth header={header}>
            {logs.data.map((row) => (
              <tr>
                <td className="text-center">
                  {row.nLevel == 1 ? (
                    <Label color="red">ERROR</Label>
                  ) : row.nLevel == 2 ? (
                    <Label color="blue">NOTICE</Label>
                  ) : row.nLevel == 3 ? (
                    <Label color="gray">DEBUG</Label>
                  ) : (
                    row.nLevel
                  )}
                </td>
                <td>{row.cLog}</td>
                <td className="text-right">
                  <ReactTimeago date={row.dDatum} />
                </td>
              </tr>
            ))}
          </DataTable>
        ))}
    </div>
  )
}

const header: Array<DataTableHeader> = [
  {
    title: 'Level',
    column: 'level',
  },
  {
    title: 'Log',
    column: 'log',
  },
  {
    title: 'Erstellt',
    column: 'created',
  },
]

export default Logs
