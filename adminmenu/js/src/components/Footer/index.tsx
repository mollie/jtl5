import React, {useEffect, useState} from 'react'
import useAPI from '../../hooks/useAPI';
import {STATIC_URL} from './../../utils'
import cls from './Footer.module.scss';

const Footer = () => {

    const api = useAPI();

    const [isTestOk, setTestOk] = useState(false);
    const [RTT, setRTT] = useState('...');

    useEffect(() => {
        checkTest();
        const interval = setInterval(() => {
            checkTest();
        }, 60000);
        return () => clearInterval(interval);
    }, [])

    const checkTest = () => {
        const start = Date.now();
        setRTT('...');
        api.run('Helper', 'test', {
            test: true
        }).then(res => {
            setRTT((Math.round(((Date.now() - start) / 1000) * 100) / 100) + "s");
            setTestOk(res.data.test === true);
        }).catch(err => {
            setTestOk(false);
            setRTT(err.toString());
        })
    }

    return <div className={'container mt-6 mx-auto'}>
        <div className={'flex flex-row items-center'}>
            <div className={'flex-grow flex flex-row ' + cls.statusIndicator}>
                <div title={`API Status`} onClick={checkTest}
                     className={['w-6 h-6 rounded-full', isTestOk ? 'bg-green-500' : 'bg-red-500'].join(' ')}/>
                <div className={cls.rtt}>{RTT}</div>
            </div>
            <div className={'flex-grow-0'}>
                <strong>WebStollen</strong> - plugin essentials
            </div>
            <div className={'flex-grow-0 px-3'}>
                <img src={STATIC_URL + '/plugin/img/ws_bogen.png'}/>
            </div>
        </div>
    </div>;
}

export default Footer;