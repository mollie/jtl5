import React, { useCallback, useState } from 'react'
import { formatAmount, Loading } from '@webstollen/react-jtl-plugin/lib'
import { molliePaymentStatusLabel } from '../../../helper'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faChevronDoubleDown, faChevronDoubleLeft } from '@fortawesome/pro-regular-svg-icons'
import Button from '@webstollen/react-jtl-plugin/lib/components/Button'
import { UseMollieReturn } from '../../../hooks/useMollie'
import useErrorSnack from '../../../hooks/useErrorSnack'
import DataTable, { DataTableHeader } from '@webstollen/react-jtl-plugin/lib/components/DataTable/DataTable'
import ReactTimeago from 'react-timeago'

export type RefundsProps = {
  mollie: UseMollieReturn
}

const Refunds = ({ mollie: { data, refundOrder, refundAmount, cancelRefund, loading } }: RefundsProps) => {
  const [showRefunds, setShowRefunds] = useState(false)
  const [amountToRefund, setAmountToRefund] = useState(0.0)
  const [showError] = useErrorSnack()

  const handleRefundAmount = useCallback(
    (amount: number) => {
      if (
        window.confirm(
          `Möchten Sie wirklich '${amount.toFixed(2)} ${data?.amount.currency}' dieser Zahlung zurück erstatten?`
        )
      ) {
        refundAmount(amount)
          .then(() => setAmountToRefund(0))
          .catch(showError)
      } else {
        setAmountToRefund(0)
      }
    },
    [setAmountToRefund, showError, data?.amount.currency, refundAmount]
  )

  const handleCancel = (id: string) => {
    if (window.confirm('Diese Erstattung wirklich abbrechen?')) {
      cancelRefund(id).catch(showError)
    }
  }

  const handleRefundOrder = () => {
    if (window.confirm('Diese Bestellung bei Mollie wirklich zurück erstatten?')) {
      refundOrder().catch(showError)
    }
  }

  return (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowRefunds((prev) => !prev)}>
        Refunds ({data?._embedded?.refunds?.length ?? 0})
        <FontAwesomeIcon className=" float-right" icon={showRefunds ? faChevronDoubleDown : faChevronDoubleLeft} />
      </h3>
      {showRefunds && (
        <Loading loading={loading}>
          <div>
            {data?.id.substring(0, 3) === 'tr_' && parseFloat(data?.amountRemaining.value) > 0 && (
              <div className="flex justify-end items-stretch">
                <div className="p-2 bg-gray-400 rounded-l leading-9">Refund Amount:</div>
                <div className="p-2 bg-gray-400 leading-9">
                  <input
                    className="mx-1 text-center bg-transparent font-bold w-24"
                    step={0.01}
                    min={0}
                    value={amountToRefund}
                    onChange={(e) => setAmountToRefund(parseFloat(e.target.value.replace(',', '.')))}
                    type="number"
                  />
                </div>
                {parseFloat(data?.amountRemaining.value) > 0 && (
                  <div className="p-2 bg-gray-400 rounded-r">
                    <Button
                      light={!amountToRefund || amountToRefund <= 0}
                      disabled={!amountToRefund || amountToRefund <= 0}
                      onClick={() => handleRefundAmount(amountToRefund)}
                    >
                      refund
                    </Button>

                    <Button
                      light={parseFloat(data?.amountRemaining.value) <= 0}
                      disabled={parseFloat(data?.amountRemaining.value) <= 0}
                      onClick={() => handleRefundOrder()}
                    >
                      refund all {data?.amountRemaining.value} {data?.amountRemaining.currency}
                    </Button>
                  </div>
                )}
              </div>
            )}
            {data?._embedded?.refunds?.length ? (
              <DataTable striped fullWidth header={header}>
                {data?._embedded?.refunds?.length &&
                  data?._embedded?.refunds.map((row: Record<string, any>) => (
                    <tr>
                      <td>
                        <pre>{row.id}</pre>
                      </td>
                      <td className="text-center">{molliePaymentStatusLabel(row.status)}</td>
                      <td>{row.description}</td>
                      <td className="text-right">{formatAmount(row.amount.value, 2, row.amount.currency)}</td>
                      <td className="text-right">
                        <ReactTimeago date={row.createdAt} />
                      </td>
                      <td className="text-right">
                        {row.status === 'pending' ? (
                          <Button color="red" onClick={() => handleCancel(row.id)}>
                            cancel
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  ))}
              </DataTable>
            ) : null}
          </div>
        </Loading>
      )}
    </div>
  )
}

const header: Array<DataTableHeader> = [
  {
    title: 'ID',
    column: 'id',
  },
  {
    title: 'Status',
    column: 'status',
  },
  {
    title: 'Beschreibung',
    column: 'description',
  },
  {
    title: 'Betrag',
    column: 'amount',
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

export default Refunds
