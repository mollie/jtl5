import React, { useEffect, useState } from 'react'
import Button from '@webstollen/react-jtl-plugin/lib/components/Button'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faChevronDoubleDown, faChevronDoubleLeft, faShippingFast, faSync } from '@fortawesome/pro-regular-svg-icons'
import TextLink from '@webstollen/react-jtl-plugin/lib/components/TextLink'
import { UseMollieReturn } from '../../../hooks/useMollie'
import useShipments from '../../../hooks/useShipments'
import useErrorSnack from '../../../hooks/useErrorSnack'
import DataTable, { DataTableHeader } from '@webstollen/react-jtl-plugin/lib/components/DataTable/DataTable'

export type ShipmentsProps = {
  kBestellung: number
  mollie: UseMollieReturn
}

const Shipments = ({ mollie, kBestellung }: ShipmentsProps) => {
  const [showShipments, setShowShipments] = useState(false)
  const [showError] = useErrorSnack()
  const shipmentData = useShipments(kBestellung)

  useEffect(() => {
    shipmentData.load()
  }, [shipmentData.load])

  const syncShipping = (kLieferschein: number) => {
    shipmentData
      .sync(mollie.data?.id, kLieferschein)
      .then(async (resp) => {
        if (resp.data.error) {
          showError(resp.data.error.message)
        } else {
          await shipmentData.load()
        }
      })
      .catch(showError)
  }

  return (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1 cursor-pointer" onClick={() => setShowShipments((prev) => !prev)}>
        Lieferungen ({mollie.data?._embedded?.shipments?.length})
        <FontAwesomeIcon className=" float-right" icon={showShipments ? faChevronDoubleDown : faChevronDoubleLeft} />
      </h3>

      {showShipments && (
        <DataTable striped fullWidth header={header} loadin={shipmentData.loading}>
          {shipmentData.data?.map((row) => (
            <tr>
              <td>
                {row.shipment ? (
                  <TextLink color={'green'} href={mollie.data?._links.dashboard?.href} target="_blank">
                    {row.cLieferscheinNr}
                  </TextLink>
                ) : (
                  row.cLieferscheinNr
                )}
              </td>
              <td>{row.shipment?.cShipmentId ? row.shipment?.cShipmentId : '-'}</td>
              <td>{row.shipment?.cCarrier ? row.shipment.cCarrier : 'n/a'}</td>
              <td>
                {row.shipment?.cCode ? (
                  <TextLink color={'blue'} target={'_blank'} href={row.shipment?.cUrl}>
                    {row.shipment?.cCode}
                  </TextLink>
                ) : (
                  'n/a'
                )}
              </td>
              <td>
                {mollie.data?.status !== 'completed' && row.shipment === null && (
                  <Button title="synchronize" onClick={() => syncShipping(row.kLieferschein)}>
                    <FontAwesomeIcon icon={faSync} />
                  </Button>
                )}
                {row.cUrl && (
                  <Button>
                    <FontAwesomeIcon icon={faShippingFast} />
                  </Button>
                )}
              </td>
            </tr>
          ))}
        </DataTable>
      )}
    </div>
  )
}

const header: Array<DataTableHeader> = [
  {
    title: 'Lieferschein',
    column: 'cLieferschein',
  },
  {
    title: 'Shipment ID',
    column: 'id',
  },
  {
    title: 'Carrier',
    column: 'carrier',
  },
  {
    title: 'Code',
    column: 'code',
  },
  {
    title: '',
    column: '_actions',
  },
]

export default Shipments
