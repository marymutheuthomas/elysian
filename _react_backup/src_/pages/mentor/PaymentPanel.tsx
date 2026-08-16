import React, { useState } from 'react';
import { useProgramStore } from '../../store/useProgramStore';

export const PaymentPanel: React.FC = () => {
  const { payments, students, programs, verifyPayment, rejectPayment } = useProgramStore();
  const [filter, setFilter] = useState<'all' | 'pending' | 'verified' | 'rejected'>('all');

  const getStudentName = (uin: string) => {
    return students.find((s) => s.permanentID === uin)?.name || 'Unknown Student';
  };

  const getProgramTitle = (programId: string) => {
    return programs.find((p) => p.id === programId)?.title || 'Unknown Program';
  };

  const filteredPayments = payments.filter((payment) => {
    if (filter === 'all') return true;
    return payment.status === filter;
  });

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'pending':
        return (
          <span className="px-2.5 py-1 text-[10px] font-bold rounded-full bg-amber-500/10 text-amber-500 border border-amber-500/20">
            Pending Review
          </span>
        );
      case 'verified':
        return (
          <span className="px-2.5 py-1 text-[10px] font-bold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/25">
            Verified
          </span>
        );
      case 'rejected':
        return (
          <span className="px-2.5 py-1 text-[10px] font-bold rounded-full bg-red-500/10 text-red-400 border border-red-500/25">
            Rejected
          </span>
        );
      default:
        return null;
    }
  };

  return (
    <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 h-full flex flex-col min-h-0 text-white shadow-2xl">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-800 mb-5">
        <div>
          <h2 className="text-lg font-bold font-outfit">Payment Reconciliation</h2>
          <p className="text-[10px] text-slate-500 font-mono">
            Verify student wire transactions using the Transaction ID (TTID)
          </p>
        </div>

        {/* Tab Filters */}
        <div className="flex bg-slate-950 p-1 rounded-xl border border-slate-800">
          {(['all', 'pending', 'verified', 'rejected'] as const).map((tab) => (
            <button
              key={tab}
              onClick={() => setFilter(tab)}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold capitalize transition-all ${
                filter === tab
                  ? 'bg-amber-500 text-white'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
            >
              {tab}
            </button>
          ))}
        </div>
      </div>

      {/* Table */}
      <div className="flex-1 overflow-x-auto min-h-0 custom-scrollbar">
        {filteredPayments.length === 0 ? (
          <div className="text-center py-12 text-slate-500 text-xs">
            No payment records found matching selection.
          </div>
        ) : (
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="border-b border-slate-800 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                <th className="pb-3 px-3">Student / UIN</th>
                <th className="pb-3 px-3">Program Details</th>
                <th className="pb-3 px-3 font-mono">TTID / Fee</th>
                <th className="pb-3 px-3">Status</th>
                <th className="pb-3 px-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/50">
              {filteredPayments.map((payment) => (
                <tr key={payment.id} className="hover:bg-slate-950/25 transition-colors">
                  {/* Student Info */}
                  <td className="py-4 px-3">
                    <div className="font-bold text-slate-200">
                      {getStudentName(payment.studentPermanentID)}
                    </div>
                    <div className="text-[9px] font-mono text-slate-500 mt-0.5">
                      {payment.studentPermanentID}
                    </div>
                  </td>

                  {/* Program Info */}
                  <td className="py-4 px-3">
                    <div className="font-semibold text-slate-300">
                      {getProgramTitle(payment.programId)}
                    </div>
                    <div className="text-[9px] font-mono text-slate-500 mt-0.5">
                      PAY ID: {payment.id}
                    </div>
                  </td>

                  {/* TTID / Amount */}
                  <td className="py-4 px-3 font-mono">
                    <div className="font-bold text-amber-500 select-all">{payment.ttid}</div>
                    <div className="text-[10px] text-slate-300 font-bold mt-0.5">
                      ${payment.amount.toLocaleString()}
                    </div>
                  </td>

                  {/* Status */}
                  <td className="py-4 px-3">{getStatusBadge(payment.status)}</td>

                  {/* Actions */}
                  <td className="py-4 px-3 text-right">
                    {payment.status === 'pending' ? (
                      <div className="inline-flex gap-2">
                        <button
                          onClick={() => verifyPayment(payment.id)}
                          className="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold rounded-lg transition-all text-[10px]"
                        >
                          Approve
                        </button>
                        <button
                          onClick={() => rejectPayment(payment.id)}
                          className="px-2.5 py-1.5 bg-red-600 hover:bg-red-700 active:scale-95 text-white font-bold rounded-lg transition-all text-[10px] border border-red-900/30"
                        >
                          Reject
                        </button>
                      </div>
                    ) : (
                      <span className="text-[10px] text-slate-500 font-medium">
                        {payment.verifiedAt
                          ? `Verified: ${new Date(payment.verifiedAt).toLocaleDateString()}`
                          : 'Processed'}
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
};
