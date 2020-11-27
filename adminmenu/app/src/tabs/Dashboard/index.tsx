import React, {useEffect, useState} from "react";
import useApi from "@webstollen/react-jtl-plugin/lib/hooks/useAPI";
import {PaymentMethod2img} from "../../helper";
import {formatAmount, Loading} from "@webstollen/react-jtl-plugin/lib";

type LoadingState = {
    methods?: boolean
    statistics?: boolean
}

const Dashboard = () => {

    const [methods, setMethods] = useState<Record<string, Record<string, any>>>({});
    const [statistics, setStatistics] = useState<Record<string, Record<string, any>>>({});
    const [loading, _setLoading] = useState<LoadingState>({methods: false, statistics: false});
    const api = useApi();

    const setLoading = (key: string, value: boolean) => {
        _setLoading(prev => {
            return {...prev, [key]: value};
        })
    }

    useEffect(() => {
        setLoading('methods', true);
        api.run('mollie', 'methods')
            .then(res => setMethods(res.data))
            .catch(console.error)
            .finally(() => setLoading('methods', false));

        setLoading('statistics', true);
        api.run('mollie', 'statistics')
            .then(res => setStatistics(res.data))
            .catch(console.error)
            .finally(() => setLoading('statistics', false));
    }, [])

    return <div className="mx-2">
        <div className="mb-4 w-full bg-white rounded-md p-4 relative">
            <div className="flex items-center my-3">
                <img src="https://cdn.webstollen.de/plugins/ws_mollie_ws.svg" className="mr-2"
                     style={{maxWidth: '100px'}}/>
                <div className="text-xl">Integireren Sie alle wichtigen<br/>Zahlungsmethoden in kürzester zeit.</div>
            </div>

            <b>Derzeit mit Mollie angebunden:</b>
            <Loading loading={loading.methods}>
                {Object.keys(methods).length > 0 &&
                <div className="flex flex-wrap content-center justify-start flex-row">
                    {Object.keys(methods).map(id => methods[id].shipping.length ?
                        <div key={id} style={{flexBasis: '33%'}}>
                            <div className="m-2 p-2">
                                <PaymentMethod2img method={id}/> {methods[id].mollie.description}
                            </div>
                        </div> : null)}
                </div>}
            </Loading>
        </div>
        <div className="flex flex-row mb-3">
            <div className="bg-white rounded-md flex-1 p-4 mr-2 relative">
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