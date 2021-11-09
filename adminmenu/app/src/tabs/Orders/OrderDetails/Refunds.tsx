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

const Refunds = ({ mollie }: RefundsProps) => {
  const [showRefunds, setShowRefunds] = useState(false)
  const [refundAmount, setRefundAmount] = useState(0.0)
  const [showError] = useErrorSnack()

  const handleRefundAmount = useCallback(
    (amount: number) => {
      if (
        window.confirm(
          `Möchten Sie wirklich '${amount.toFixed(2)} ${mollie.data?.amount.currency}' dieser Zahlung zurück erstatten?`
        )
      ) {
        mollie
          .refundAmount(amount)
          .then(() => setRefundAmount(0))
          .catch((e) => showError(`${e}`))
      } else {
        setRefundAmount(0)
      }
    },
    [setRefundAmount, mollie.refundAmount, showError]
  )

  const handleRefundOrder = () => {
    if (window.confirm('Diese Bestellung bei Mollie wirklich zurück erstatten?')) {
      mollie.refundOrder().catch((e) => showError(`${e}`))
    }
  }

  // TODO: Cancel pending Refunds

  return (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowRefunds((prev) => !prev)}>
        Refunds ({mollie.data?._embedded?.refunds?.length ?? 0})
        <FontAwesomeIcon className=" float-right" icon={showRefunds ? faChevronDoubleDown : faChevronDoubleLeft} />
      </h3>
      {showRefunds && (
        <Loading loading={mollie.loading}>
          <div>
            {mollie.data?.id.substring(0, 3) === 'tr_' && parseFloat(mollie.data?.amountRemaining.value) > 0 && (
              <div className="flex justify-end items-stretch">
                <div className="p-2 bg-gray-400 rounded-l leading-9">Refund Amount:</div>
                <div className="p-2 bg-gray-400 leading-9">
                  <input
                    className="mx-1 text-center bg-transparent font-bold w-24"
                    step={0.01}
                    min={0}
                    value={refundAmount}
                    onChange={(e) => setRefundAmount(parseFloat(e.target.value.replace(',', '.')))}
                    type="number"
                  />
                </div>
                {parseFloat(mollie.data?.amountRemaining.value) > 0 && (
                  <div className="p-2 bg-gray-400 rounded-r">
                    <Button
                      light={!refundAmount || refundAmount <= 0}
                      disabled={!refundAmount || refundAmount <= 0}
                      onClick={() => handleRefundAmount(refundAmount)}
                    >
                      refund
                    </Button>

                    <Button
                      light={parseFloat(mollie.data?.amountRemaining.value) <= 0}
                      disabled={parseFloat(mollie.data?.amountRemaining.value) <= 0}
                      onClick={() => handleRefundOrder()}
                    >
                      refund all {mollie.data?.amountRemaining.value} {mollie.data?.amountRemaining.currency}
                    </Button>
                  </div>
                )}
              </div>
            )}
            {mollie.data?._embedded?.refunds?.length ? (
              <DataTable striped fullWidth header={header}>
                {mollie.data?._embedded?.refunds?.length &&
                  mollie.data?._embedded?.refunds.map((row: Record<string, any>) => (
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
                      <td></td>
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
