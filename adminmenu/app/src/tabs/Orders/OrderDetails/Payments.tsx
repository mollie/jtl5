import React, { useContext, useState } from 'react'
import TextLink from '@webstollen/react-jtl-plugin/lib/components/TextLink'
import { formatAmount } from '@webstollen/react-jtl-plugin/lib'
import { molliePaymentStatusLabel, PaymentMethod2img } from '../../../helper'
import { faChevronDoubleDown, faChevronDoubleLeft, faEdit } from '@fortawesome/pro-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { UseMollieReturn } from '../../../hooks/useMollie'
import DataTable, { DataTableHeader } from '@webstollen/react-jtl-plugin/lib/components/DataTable/DataTable'
import ReactTimeago from 'react-timeago'
import MollieContext from '../../../context/MollieContext'

const Payments = () => {
  const mollie = useContext<UseMollieReturn>(MollieContext)
  const [showPayments, setShowPayments] = useState(false)
  const payments = mollie.data?._embedded?.payments || []

  return payments.length ? (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowPayments((prev) => !prev)}>
        Zahlungen ({payments.length})
        <FontAwesomeIcon className=" float-right" icon={showPayments ? faChevronDoubleDown : faChevronDoubleLeft} />
      </h3>
      {showPayments && (
        <DataTable fullWidth striped header={header}>
          {payments.map((row: Record<string, any>) => (
            <tr>
              <td>{row.id}</td>
              <td>{molliePaymentStatusLabel(row.status)}</td>
              <td>
                <PaymentMethod2img method={row.method} />
              </td>
              <td>{formatAmount(row.amount.value, 2, row.amount.currency)}</td>
              <td>
                {row.settlementAmount?.value
                  ? formatAmount(row.settlementAmount.value, 2, row.settlementAmount.currency)
                  : '-'}
              </td>
              <td>
                {row.amountRefunded?.value
                  ? formatAmount(row.amountRefunded.value, 2, row.amountRefunded.currency)
                  : '-'}
              </td>
              <td>
                {row.amountRemaining?.value
                  ? formatAmount(row.amountRemaining.value, 2, row.amountRemaining.currency)
                  : '-'}
              </td>
              <td>
                <pre style={{ fontSize: '9px' }}>{JSON.stringify(row.details, null, 2)}</pre>
              </td>
              <td className="text-right">
                <ReactTimeago date={row.createdAt} />
              </td>
              <td>
                {row._links.changePaymentState && (
                  <TextLink href={row._links.changePaymentState.href} target="_blank">
                    <FontAwesomeIcon icon={faEdit} />
                  </TextLink>
                )}
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
    title: 'Status',
    column: 'status',
  },
  {
    title: 'Methode',
    column: 'method',
  },
  {
    title: 'Betrag',
    column: 'amount',
  },
  {
    title: 'Settlement',
    column: 'settlement',
  },
  {
    title: 'Refunded',
    column: 'refunded',
  },
  {
    title: 'Remaining',
    column: 'remaining',
  },
  {
    title: 'Details',
    column: 'details',
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

export default Payments
