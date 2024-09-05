import React, { useEffect, useState } from 'react'
import { Label, Loading } from '@webstollen/react-jtl-plugin/lib'
import { faChevronDoubleDown, faChevronDoubleLeft, faSync } from '@fortawesome/pro-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import usePaymentLogs from '../../../hooks/usePaymentLogs'
import DataTable, { DataTableHeader } from '@webstollen/react-jtl-plugin/lib/components/DataTable/DataTable'
import { parseInt } from 'lodash'
import Alert from '@webstollen/react-jtl-plugin/lib/components/Alert'

export type LogsProps = {
  mollieId: string
  kBestellung: number
}

const Logs = ({ kBestellung, mollieId }: LogsProps) => {
  const [showLogs, setShowLogs] = useState(false)
  const { load, loading, data, error } = usePaymentLogs(kBestellung, mollieId)

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
        Logs {data ? <>({data?.length ?? 0})</> : null}
        <FontAwesomeIcon className="float-right" icon={showLogs ? faChevronDoubleDown : faChevronDoubleLeft} />
        {showLogs && (
          <FontAwesomeIcon icon={faSync} onClick={reload} className="cursor-pointer float-right mx-2" fixedWidth />
        )}
      </h3>
      {showLogs &&
        (error ? (
          <Alert variant="error">{error}</Alert>
        ) : (
          <Loading loading={loading}>
            <DataTable striped fullWidth header={header} loading={loading}>
              {data?.map((row) => (
                <tr key={row.kZahlunglog}>
                  <td className="text-center">
                    {parseInt(row.nLevel) === 1 ? (
                      <Label color="red">ERROR</Label>
                    ) : parseInt(row.nLevel) === 2 ? (
                      <Label color="blue">NOTICE</Label>
                    ) : parseInt(row.nLevel) === 3 ? (
                      <Label color="gray">DEBUG</Label>
                    ) : (
                      row.nLevel
                    )}
                  </td>
                  <td>{row.cLog}</td>
                  <td className="text-left">
                    {new Date(row.dDatum).toLocaleDateString('de-DE')} {new Date(row.dDatum).toLocaleTimeString('de-DE')}
                  </td>
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
