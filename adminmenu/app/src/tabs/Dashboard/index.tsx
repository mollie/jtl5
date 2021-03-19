import React, {useCallback, useEffect, useState} from "react";
import useApi from "@webstollen/react-jtl-plugin/lib/hooks/useAPI";
import {PaymentMethod2img} from "../../helper";
import {formatAmount, Loading, usePluginInfo} from "@webstollen/react-jtl-plugin/lib";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import {faCog, faCreditCard, faMoneyBill, faShippingFast, faSync} from "@fortawesome/pro-regular-svg-icons";
import setupImg from '../../assets/img/mollie-dashboard.png';
import Button from "@webstollen/react-jtl-plugin/lib/components/Button";

type LoadingState = {
    methods?: boolean
    statistics?: boolean
}

const Dashboard = () => {

    const [methods, setMethods] = useState<Record<string, Record<string, any>>>({});
    const [statistics, setStatistics] = useState<Record<string, Record<string, any>>>({});
    const [loading, _setLoading] = useState<LoadingState>({methods: false, statistics: false});
    const [setup, setSetup] = useState(true);
    const pInfo = usePluginInfo();
    const prefix = pInfo.endpoint.substring(0, pInfo.endpoint.lastIndexOf('/')) + '/app/build';
    const api = useApi();

    const setLoading = (key: string, value: boolean) => {
        _setLoading(prev => {
            return {...prev, [key]: value};
        })
    }

    const loadMethods = useCallback(() => {
        setLoading('methods', true);
        api.run('mollie', 'methods')
            .then(res => {
                setMethods(res.data)
                setSetup(false);
            })
            .catch(console.error)
            .finally(() => {
                setLoading('methods', false);
            });
    }, [api])

    const loadStatistics = useCallback(() => {
        setLoading('statistics', true);
        api.run('mollie', 'statistics')
            .then(res => setStatistics(res.data))
            .catch(console.error)
            .finally(() => setLoading('statistics', false));
    }, [api]);

    useEffect(() => {
        loadMethods()
        loadStatistics()
    }, [loadStatistics, loadMethods]);

    return <div className="mx-2">
        <div className="mb-4 w-full bg-white rounded-md p-4 relative">

            <FontAwesomeIcon onClick={loadMethods} spin={loading.methods} icon={faSync} size={"lg"}
                             className="float-right cursor-pointer"
                             title="aktualisiert"/>

            {setup ? <>
                    <img src={prefix + setupImg} alt="Setup Assistant" className="mx-auto"/>
                    <Button onClick={() => window.location.href = "https://ws-url.de/mollie-pay"} color="green"
                            className="mx-auto block my-6">
                        Jetzt kostenlos Mollie Account anlegen!
                    </Button>
                </> :
                <div className="flex items-center my-3">
                    <img src="https://cdn.webstollen.de/plugins/ws_mollie_ws.svg"
                         alt="Plugin Icon"
                         className="mr-2 max-w-full"
                         style={{maxWidth: '100px'}}/>
                    <div className="text-xl">Integireren Sie alle wichtigen<br/>Zahlungsmethoden in kürzester zeit.
                    </div>
                </div>}


            <Loading loading={loading.methods}>

                {Object.keys(methods).length > 0 && <>
                    <b>Derzeit mit Mollie angebunden:</b>
                    <div className="flex flex-wrap content-center justify-start flex-row">
                        {Object.keys(methods).map(id => methods[id].shipping.length ?
                            <div key={id} style={{flexBasis: '33%'}}>
                                <div className="m-2 p-2 border-b">
                                    <PaymentMethod2img method={id}/> {methods[id].mollie.description}
                                    <div className="float-right">
                                        <FontAwesomeIcon icon={faShippingFast}
                                                         onClick={() => alert(methods[id].shipping && methods[id].shipping?.length ? "Mit folgende Versandarten verknüpft:\n\n" + methods[id].shipping.map((shipping: Record<string, any>) => ' - ' + shipping.cName).join("\n") : 'Mit keiner Versandart verknüpft!')}
                                                         className="cursor-help"
                                                         color={methods[id].shipping && methods[id].shipping?.length ? 'green' : 'red'}
                                                         title={methods[id].shipping && methods[id].shipping?.length ? methods[id].shipping.map((shipping: Record<string, any>) => shipping.cName).join(', ') : 'n/a'}/>
                                        {methods[id].components ? <>
                                            {methods[id].components === 'S' || methods[id].components === 'Y' ?
                                                <FontAwesomeIcon className={"ml-1 cursor-help"} icon={faCreditCard}
                                                                 onClick={() => alert("Mollie Components sind aktiviert: " + (methods[id].components === 'S' ? 'optional.' : 'obligatorisch.'))}
                                                                 title={"Mollie Components enabled." + (methods[id].components === 'S' ? ' (optional)' : ' (obligatorisch)')}
                                                                 color={methods[id].components === 'S' ? 'green' : 'blue'}/>
                                                : <FontAwesomeIcon className={"ml-1 cursor-help"} icon={faCreditCard}
                                                                   onClick={() => alert("Mollie Components sind deaktiviert.")}
                                                                   color={'red'}
                                                                   title={'Mollie Components disabled'}/>
                                            }
                                        </> : null}
                                        {methods[id].api ? (
                                            methods[id].api === 'payment' ?
                                                <FontAwesomeIcon className={"cursor-help ml-1"} icon={faMoneyBill}
                                                                 onClick={() => alert("Payment API ist deaktiviert.")}
                                                                 title={'Payment API'} color={"green"}/> : null
                                        ) : null}
                                        <a title="Einstellungen" href={methods[id].settings}
                                           className={"ml-1"}
                                           target="_blank" rel="noreferrer">
                                            <FontAwesomeIcon icon={faCog}/>
                                        </a>
                                    </div>
                                </div>
                            </div> : null)}
                    </div>
                </>}

            </Loading>

            <Button onClick={() => window.location.href = "versandarten.php"} color="blue"
                    className="mx-auto block my-6">
                Zu den Versandarten
            </Button>

        </div>

        <div className="flex flex-row mb-3">
            <div className="bg-white rounded-md flex-1 p-4 mr-2 relative">
                <FontAwesomeIcon onClick={loadStatistics} spin={loading.statistics} icon={faSync} size={"lg"}
                                 className="float-right cursor-pointer" title="aktualisieren"/>
                <b>Mollie Umsätze:</b>
                <Loading loading={loading.statistics}>
                    <div className="flex flex-row justify-around items-baseline place-items-center my-5">

                        {statistics.day && <div className="flex flex-col">
                            <div className="font-semibold">{formatAmount(statistics.day.amount, 2, "€")}</div>
                            <div className="text-ws_gray-normal text-sm">last day</div>
                        </div>}
                        {statistics.week && <div className="flex flex-col">
                            <div className="font-semibold">{formatAmount(statistics.week.amount, 2, "€")}</div>
                            <div className="text-ws_gray-normal text-sm">last week</div>
                        </div>}
                        {statistics.month && <div className="flex flex-col">
                            <div className="font-semibold">{formatAmount(statistics.month.amount, 2, "€")}</div>
                            <div className="text-ws_gray-normal text-sm">last month</div>
                        </div>}
                        {statistics.year && <div className="flex flex-col">
                            <div className="font-semibold">{formatAmount(statistics.year.amount, 2, "€")}</div>
                            <div className="text-ws_gray-normal text-sm">last year</div>
                        </div>}
                    </div>

                </Loading>
            </div>
            <div className="bg-white rounded-md flex-1 p-4 ml-2 relative">
                <FontAwesomeIcon onClick={loadStatistics} spin={loading.statistics} icon={faSync} size={"lg"}
                                 className="float-right cursor-pointer" title="aktualisieren"/>
                <b>Mollie Transaktionen:</b>
                <Loading loading={loading.statistics}>
                    <div className="flex flex-row justify-around items-baseline place-items-center my-5">
                        {statistics.day && <div className="flex flex-col">
                            <div className="font-semibold">{statistics.day.transactions}</div>
                            <div className="text-ws_gray-normal text-sm">last day</div>
                        </div>}
                        {statistics.week && <div className="flex flex-col">
                            <div className="font-semibold">{statistics.week.transactions}</div>
                            <div className="text-ws_gray-normal text-sm">last week</div>
                        </div>}
                        {statistics.month && <div className="flex flex-col">
                            <div className="font-semibold">{statistics.month.transactions}</div>
                            <div className="text-ws_gray-normal text-sm">last month</div>
                        </div>}
                        {statistics.year && <div className="flex flex-col">
                            <div className="font-semibold">{statistics.year.transactions}</div>
                            <div className="text-ws_gray-normal text-sm">last year</div>
                        </div>}
                    </div>

                </Loading>
            </div>
        </div>
    </div>
}

export default Dashboard;